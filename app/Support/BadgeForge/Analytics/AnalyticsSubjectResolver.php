<?php

namespace App\Support\BadgeForge\Analytics;

use App\Enums\BadgeViewSubject;
use App\Models\BadgeAsset;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeEndpoint;
use App\Models\User;

/**
 * Resolve os nomes exibidos dos sujeitos de analytics (F7, ADR-086).
 *
 * O bruto guarda so `subject_type` + ULID; o nome vem do dono na hora da
 * leitura. Sao tres consultas por lote, uma por tipo, sempre limitadas ao
 * `user_id` do dono - um ULID de outra conta nunca resolve nome aqui.
 */
final class AnalyticsSubjectResolver
{
    /**
     * @param  list<array{type: BadgeViewSubject, id: string}>  $subjects
     * @return array<string, string> chave `"{type}:{id}"` => nome
     */
    public function names(User $user, array $subjects): array
    {
        /** @var array<string, list<string>> $ids */
        $ids = [];

        foreach ($subjects as $subject) {
            $ids[$subject['type']->value][] = $subject['id'];
        }

        $names = [];

        foreach ($ids as $type => $subjectIds) {
            $subjectIds = array_values(array_unique($subjectIds));
            $ownerId = $user->getKey();

            $query = match (BadgeViewSubject::from($type)) {
                BadgeViewSubject::Personal => BadgeAsset::query()->where('user_id', $ownerId),
                BadgeViewSubject::Endpoint => BadgeEndpoint::query()->where('user_id', $ownerId),
                BadgeViewSubject::Custom => BadgeCustomProvider::query()->where('user_id', $ownerId),
            };

            foreach ($query->whereIn('id', $subjectIds)->pluck('name', 'id') as $id => $name) {
                $names[$type.':'.(string) $id] = (string) $name;
            }
        }

        return $names;
    }
}
