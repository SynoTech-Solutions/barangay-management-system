<?php

namespace App\Http\Requests\Blotter;

use App\Domain\Residents\NameNormalizer;
use App\Enums\BlotterPartyRole;
use App\Enums\LookupType;
use App\Models\Blotter;
use App\Rules\ActiveResident;
use App\Rules\LookupOption;
use Illuminate\Foundation\Http\FormRequest;

class BlotterRequest extends FormRequest
{
    public const int MAX_PARTIES_PER_ROLE = 20;

    public const int NARRATIVE_MAX = 10_000;

    private const int TEXT_MAX = 255;

    private const array PARTY_GROUPS = [
        'complainants' => BlotterPartyRole::Complainant,
        'respondents' => BlotterPartyRole::Respondent,
    ];

    protected function prepareForValidation(): void
    {
        $normalized = ['place' => is_string($this->input('place')) ? NameNormalizer::normalize($this->input('place')) : null];

        foreach (array_keys(self::PARTY_GROUPS) as $group) {
            $normalized[$group] = array_map(fn (mixed $party): mixed => is_array($party) ? [
                ...$party,
                'name' => is_string($party['name'] ?? null) ? NameNormalizer::normalize($party['name']) : null,
                'address' => is_string($party['address'] ?? null) ? NameNormalizer::normalize($party['address']) : null,
            ] : $party, (array) $this->input($group, []));
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $blotter = $this->route('blotter');
        $blotter = $blotter instanceof Blotter ? $blotter : null;

        return [
            'incident_type_id' => ['required', new LookupOption(LookupType::BlotterIncidentType, $blotter?->incident_type_id)],
            'incident_at' => ['required', 'date', 'before_or_equal:now'],
            'place' => ['nullable', 'string', 'max:'.self::TEXT_MAX],
            'narrative' => ['required', 'string', 'max:'.self::NARRATIVE_MAX],
            'complainants' => ['required', 'array', 'min:1', 'max:'.self::MAX_PARTIES_PER_ROLE],
            'respondents' => ['present', 'array', 'max:'.self::MAX_PARTIES_PER_ROLE],
            ...$this->partyRules('complainants'),
            ...$this->partyRules('respondents'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [
            'incident_type_id' => $this->integer('incident_type_id'),
            'incident_at' => $this->string('incident_at')->toString(),
            'place' => $this->filled('place') ? $this->string('place')->toString() : null,
            'narrative' => trim($this->string('narrative')->toString()),
        ];
    }

    /**
     * @return list<array{role: BlotterPartyRole, resident_id: int|null, name: string|null, address: string|null}>
     */
    public function parties(): array
    {
        $parties = [];
        foreach (self::PARTY_GROUPS as $group => $role) {
            foreach ((array) $this->input($group, []) as $party) {
                $party = (array) $party;
                $residentId = is_numeric($party['resident_id'] ?? null) ? (int) $party['resident_id'] : null;
                $parties[] = [
                    'role' => $role,
                    'resident_id' => $residentId,
                    'name' => $residentId === null && is_string($party['name'] ?? null) ? $party['name'] : null,
                    'address' => $residentId === null && is_string($party['address'] ?? null) ? $party['address'] : null,
                ];
            }
        }

        return $parties;
    }

    /**
     * @return array<string, mixed>
     */
    private function partyRules(string $group): array
    {
        return [
            "{$group}.*.resident_id" => ['nullable', "required_without:{$group}.*.name", new ActiveResident],
            "{$group}.*.name" => ['nullable', "required_without:{$group}.*.resident_id", 'string', 'max:'.self::TEXT_MAX],
            "{$group}.*.address" => ['nullable', 'string', 'max:'.self::TEXT_MAX],
        ];
    }
}
