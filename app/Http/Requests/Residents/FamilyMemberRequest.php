<?php

namespace App\Http\Requests\Residents;

use App\Domain\Residents\NameNormalizer;
use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Models\FamilyMember;
use App\Models\Resident;
use App\Rules\ActiveResident;
use App\Rules\LookupOption;
use App\Services\Features\FeatureFlags;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FamilyMemberRequest extends FormRequest
{
    private const int FULL_NAME_MAX = 320;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('full_name'))) {
            $this->merge(['full_name' => NameNormalizer::normalize($this->input('full_name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(FeatureFlags $features): array
    {
        $resident = $this->route('resident');
        $member = $this->route('familyMember');
        $residentId = $resident instanceof Resident ? $resident->id : null;
        $memberId = $member instanceof FamilyMember ? $member->id : null;

        return [
            'relationship_id' => ['required', new LookupOption(LookupType::Relationship, $member instanceof FamilyMember ? $member->relationship_id : null)],
            'related_resident_id' => $features->enabled(FeatureFlag::FamilyMemberLinking)
                ? [
                    'nullable',
                    new ActiveResident($residentId),
                    Rule::unique('family_members', 'related_resident_id')
                        ->where('resident_id', $residentId)
                        ->whereNull('deleted_at')
                        ->ignore($memberId),
                ]
                : ['prohibited'],
            'full_name' => ['nullable', 'required_without:related_resident_id', 'string', 'max:'.self::FULL_NAME_MAX],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array{relationship_id: int, full_name: string|null, birthdate: string|null, related_resident_id: int|null}
     */
    public function member(): array
    {
        return [
            'relationship_id' => $this->integer('relationship_id'),
            'full_name' => $this->filled('full_name') ? $this->string('full_name')->toString() : null,
            'birthdate' => $this->filled('birthdate') ? $this->string('birthdate')->toString() : null,
            'related_resident_id' => $this->filled('related_resident_id') ? $this->integer('related_resident_id') : null,
        ];
    }
}
