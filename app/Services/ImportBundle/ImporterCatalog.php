<?php

namespace App\Services\ImportBundle;

use App\Services\ImportBundle\Importers\AccountsImporter;
use App\Services\ImportBundle\Importers\BankAccountsImporter;
use App\Services\ImportBundle\Importers\BarangayClearancesImporter;
use App\Services\ImportBundle\Importers\BlottersImporter;
use App\Services\ImportBundle\Importers\BudgetsImporter;
use App\Services\ImportBundle\Importers\BusinessClearancesImporter;
use App\Services\ImportBundle\Importers\BusinessesImporter;
use App\Services\ImportBundle\Importers\CedulasImporter;
use App\Services\ImportBundle\Importers\ChecksImporter;
use App\Services\ImportBundle\Importers\CollectionTypesImporter;
use App\Services\ImportBundle\Importers\ColorCategoriesImporter;
use App\Services\ImportBundle\Importers\EducationsImporter;
use App\Services\ImportBundle\Importers\FamilyMembersImporter;
use App\Services\ImportBundle\Importers\FormPicklistsImporter;
use App\Services\ImportBundle\Importers\FormTemplatesImporter;
use App\Services\ImportBundle\Importers\FormTransactionsImporter;
use App\Services\ImportBundle\Importers\IdCardsImporter;
use App\Services\ImportBundle\Importers\IndigencyCertificatesImporter;
use App\Services\ImportBundle\Importers\LiquidationsImporter;
use App\Services\ImportBundle\Importers\LookupsImporter;
use App\Services\ImportBundle\Importers\OtherCollectionsImporter;
use App\Services\ImportBundle\Importers\PuroksImporter;
use App\Services\ImportBundle\Importers\ResidentsImporter;
use App\Services\ImportBundle\Importers\SmsGroupsImporter;
use App\Services\ImportBundle\Importers\SmsTemplatesImporter;
use App\Services\ImportBundle\Importers\UsersImporter;
use App\Services\ImportBundle\Importers\VendorsImporter;
use App\Services\ImportBundle\Importers\VouchersImporter;
use App\Services\ImportBundle\Importers\WorkHistoriesImporter;
use Illuminate\Contracts\Container\Container;

final readonly class ImporterCatalog
{
    public const array IMPORTERS = [
        LookupsImporter::class,
        PuroksImporter::class,
        UsersImporter::class,
        ColorCategoriesImporter::class,
        ResidentsImporter::class,
        FamilyMembersImporter::class,
        EducationsImporter::class,
        WorkHistoriesImporter::class,
        IdCardsImporter::class,
        BarangayClearancesImporter::class,
        IndigencyCertificatesImporter::class,
        BusinessesImporter::class,
        BusinessClearancesImporter::class,
        CedulasImporter::class,
        BlottersImporter::class,
        CollectionTypesImporter::class,
        OtherCollectionsImporter::class,
        AccountsImporter::class,
        BudgetsImporter::class,
        BankAccountsImporter::class,
        VendorsImporter::class,
        VouchersImporter::class,
        ChecksImporter::class,
        LiquidationsImporter::class,
        FormPicklistsImporter::class,
        FormTemplatesImporter::class,
        FormTransactionsImporter::class,
        SmsGroupsImporter::class,
        SmsTemplatesImporter::class,
    ];

    public function __construct(private Container $container) {}

    /**
     * @return list<EntityImporter>
     */
    public function importers(): array
    {
        return array_map(fn (string $class): EntityImporter => $this->container->make($class), self::IMPORTERS);
    }
}
