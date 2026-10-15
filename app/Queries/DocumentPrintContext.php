<?php

namespace App\Queries;

use App\Domain\Money\Centavos;
use App\Domain\Residents\AddressComposer;
use App\Enums\FeatureFlag;
use App\Enums\SignatoryOffice;
use App\Models\Barangay;
use App\Models\BarangayProfile;
use App\Models\IssuedDocument;
use App\Models\Resident;
use App\Models\Signatory;
use App\Services\Features\FeatureFlags;
use App\Support\StoragePaths;
use App\Tenancy\CurrentBarangay;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

final readonly class DocumentPrintContext
{
    public const string DATE_FORMAT = 'Y-m-d';

    private const int QR_SIZE = 160;

    private const string SVG_DATA_URI = 'data:image/svg+xml;base64,';

    public function __construct(
        private CurrentBarangay $currentBarangay,
        private FeatureFlags $features,
    ) {}

    public function barangay(): Barangay
    {
        return $this->currentBarangay->require()->loadMissing('municipality.province');
    }

    /**
     * @return array{barangay: string, municipality: string, province: string|null, telephone: string|null, ordinance_no: string|null}
     */
    public function header(): array
    {
        $barangay = $this->barangay();
        $profile = BarangayProfile::query()->find($barangay->id);

        return [
            'barangay' => $barangay->name,
            'municipality' => $barangay->municipality->name,
            'province' => $barangay->municipality->province?->name,
            'telephone' => $profile?->telephone,
            'ordinance_no' => $profile?->id_card_ordinance_no,
        ];
    }

    public function residentAddress(Resident $resident): string
    {
        $resident->loadMissing('purok:id,name');
        $barangay = $this->barangay();

        return $this->features->enabled(FeatureFlag::DefaultAddress)
            ? AddressComposer::compose([$resident->purok?->name, $barangay->name, $barangay->municipality->name, $barangay->municipality->province?->name])
            : AddressComposer::compose([$resident->street_address]);
    }

    /**
     * @return array{resident_id: int, resident_number: string, first_name: string, middle_name: string|null, last_name: string, name_extension: string|null, birthdate: string|null, birthplace: string|null, gender: string|null, civil_status: string|null, nationality: string|null, address: string, has_photo: bool, photo_version: int|null}
     */
    public function residentFacts(Resident $resident): array
    {
        $resident->loadMissing(['purok:id,name', 'birthplace:id,name', 'nationality:id,label']);

        return [
            'resident_id' => $resident->id,
            'resident_number' => $resident->resident_number,
            'first_name' => $resident->first_name,
            'middle_name' => $resident->middle_name,
            'last_name' => $resident->last_name,
            'name_extension' => $resident->name_extension,
            'birthdate' => $resident->birthdate?->format(self::DATE_FORMAT),
            'birthplace' => $resident->birthplace?->name,
            'gender' => $resident->gender === null ? null : (string) __($resident->gender->labelKey()),
            'civil_status' => $resident->civil_status === null ? null : (string) __($resident->civil_status->labelKey()),
            'nationality' => $resident->nationality?->label,
            'address' => $this->residentAddress($resident),
            'has_photo' => $resident->photo_path !== null && $this->features->enabled(FeatureFlag::ResidentPhoto),
            'photo_version' => $resident->photo_taken_at?->getTimestamp(),
        ];
    }

    /**
     * @return array{name: string, title: string|null, signature: string|null}|null
     */
    public function signatory(?Signatory $signatory, bool $withSignature = true): ?array
    {
        if ($signatory === null) {
            return null;
        }

        return [
            'name' => $signatory->name,
            'title' => $signatory->title,
            'signature' => $withSignature ? $this->signatureDataUri($signatory->signature_path) : null,
        ];
    }

    /**
     * @return array{name: string|null, title: string|null, signature: string|null}
     */
    public function certifier(IssuedDocument $document): array
    {
        $captain = $this->features->enabled(FeatureFlag::CaptainSignatureOnClearance)
            ? Signatory::forOffices(SignatoryOffice::PunongBarangay)->get(SignatoryOffice::PunongBarangay->value)
            : null;

        return [
            'name' => $document->certified_by_name,
            'title' => $document->certified_by_title,
            'signature' => $captain !== null && $captain->name === $document->certified_by_name ? $this->signatureDataUri($captain->signature_path) : null,
        ];
    }

    /**
     * @return array{print_count: int, is_reprint: bool, qr: string|null}
     */
    public function printMarks(IssuedDocument $document): array
    {
        return [
            'print_count' => $document->print_count,
            'is_reprint' => $document->isReprint(),
            'qr' => $this->features->enabled(FeatureFlag::ClearanceQrCode) ? $this->qr($document->documentNumber()) : null,
        ];
    }

    /**
     * @return array{or_number: string|null, amount: string, doc_stamp: string|null, total: string, ctc: array{number: string|null, place: string|null, date: string|null}|null}
     */
    public function payment(IssuedDocument $document, string $docStamp): array
    {
        $showDocStamp = $this->features->enabled(FeatureFlag::ClearanceDocStamp);
        $showCtc = $this->features->enabled(FeatureFlag::ClearanceCtcReference) && $document->ctc_number !== null;

        return [
            'or_number' => $document->or_number,
            'amount' => $document->amount,
            'doc_stamp' => $showDocStamp ? $docStamp : null,
            'total' => $showDocStamp ? Centavos::sum($document->amount, $docStamp) : $document->amount,
            'ctc' => $showCtc ? [
                'number' => $document->ctc_number,
                'place' => $document->ctc_place,
                'date' => $document->ctc_date?->format(self::DATE_FORMAT),
            ] : null,
        ];
    }

    public function qr(string $text): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(self::QR_SIZE), new SvgImageBackEnd));

        return self::SVG_DATA_URI.base64_encode($writer->writeString($text));
    }

    private function signatureDataUri(?string $path): ?string
    {
        $disk = Storage::disk(StoragePaths::PRIVATE_DISK);

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);
        $mime = $disk->mimeType($path);

        return $contents === null || $mime === false ? null : 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
