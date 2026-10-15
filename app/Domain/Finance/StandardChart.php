<?php

namespace App\Domain\Finance;

use App\Enums\AccountGroupType as Type;
use App\Enums\AccountKind as Kind;

final class StandardChart
{
    /**
     * @return list<array{code: string, name: string, kind: Kind, parent: string|null, group_type: Type|null}>
     */
    public static function entries(): array
    {
        return [
            self::group('1', 'Income', Type::Income),
            self::title('1-01', 'Real Property Tax Share', '1'),
            self::title('1-02', 'Internal Revenue Allotment', '1'),
            self::title('1-03', 'Clearance and Certification Fees', '1'),
            self::title('1-04', 'Community Tax Share', '1'),
            self::title('1-05', 'Other Receipts', '1'),

            self::group('2', 'Expenditures', Type::Expense),
            self::title('2-01', 'Personal Services', '2'),
            self::subsidiary('2-01-01', 'Honoraria', '2-01'),
            self::subsidiary('2-01-02', 'Cash Gift', '2-01'),
            self::subsidiary('2-01-03', 'Year-End Bonus', '2-01'),
            self::title('2-02', 'Maintenance and Other Operating Expenses', '2'),
            self::subsidiary('2-02-01', 'Traveling Expenses', '2-02'),
            self::subsidiary('2-02-02', 'Training Expenses', '2-02'),
            self::subsidiary('2-02-03', 'Office Supplies', '2-02'),
            self::subsidiary('2-02-04', 'Utilities', '2-02'),
            self::subsidiary('2-02-05', 'Repairs and Maintenance', '2-02'),
            self::title('2-03', 'Capital Outlay', '2'),
            self::subsidiary('2-03-01', 'Infrastructure Projects', '2-03'),
            self::subsidiary('2-03-02', 'Office Equipment', '2-03'),
            self::title('2-04', 'Special Purpose Appropriations', '2'),
            self::subsidiary('2-04-01', 'Disaster Risk Reduction and Management Fund', '2-04'),
            self::subsidiary('2-04-02', 'Development Fund', '2-04'),
            self::subsidiary('2-04-03', 'Youth Council Fund', '2-04'),

            self::group('3', 'Other Accounts', Type::Other),
            self::title('3-01', 'Trust Fund Receipts', '3'),
        ];
    }

    /**
     * @return array{code: string, name: string, kind: Kind, parent: string|null, group_type: Type|null}
     */
    private static function group(string $code, string $name, Type $type): array
    {
        return ['code' => $code, 'name' => $name, 'kind' => Kind::Group, 'parent' => null, 'group_type' => $type];
    }

    /**
     * @return array{code: string, name: string, kind: Kind, parent: string|null, group_type: Type|null}
     */
    private static function title(string $code, string $name, string $parent): array
    {
        return ['code' => $code, 'name' => $name, 'kind' => Kind::Title, 'parent' => $parent, 'group_type' => null];
    }

    /**
     * @return array{code: string, name: string, kind: Kind, parent: string|null, group_type: Type|null}
     */
    private static function subsidiary(string $code, string $name, string $parent): array
    {
        return ['code' => $code, 'name' => $name, 'kind' => Kind::Subsidiary, 'parent' => $parent, 'group_type' => null];
    }
}
