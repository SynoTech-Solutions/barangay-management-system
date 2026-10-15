<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Banknote,
    BookOpen,
    ClipboardCheck,
    Coins,
    FileSpreadsheet,
    FileStack,
    ListPlus,
    ScrollText,
    TableProperties,
    Landmark,
    Truck,
    Wallet,
    BarChart3,
    Briefcase,
    CalendarX,
    FilePlus,
    FileCheck,
    HandHeart,
    Receipt,
    ReceiptText,
    ShieldAlert,
    Store,
    Building2,
    ClipboardList,
    FileText,
    History,
    IdCard,
    Palette,
    PieChart,
    MapPinned,
    UserPlus,
    UsersRound,
    LayoutGrid,
    ListChecks,
    MessageSquare,
    MessageSquarePlus,
    MessagesSquare,
    NotebookText,
    Settings2,
    ToggleRight,
    UserCog,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import BarangaySwitcher from '@/components/BarangaySwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { dashboard } from '@/routes';
import { index as auditIndex } from '@/routes/admin/audit';
import { edit as barangayProfileEdit } from '@/routes/admin/barangay-profile';
import { index as featuresIndex } from '@/routes/admin/features';
import { index as lookupsIndex } from '@/routes/admin/lookups';
import { index as usersIndex } from '@/routes/admin/users';
import {
    create as businessClearancesCreate,
    expired as expiredBusinesses,
    index as businessClearancesIndex,
} from '@/routes/business-clearances';
import {
    create as businessesCreate,
    index as businessesIndex,
} from '@/routes/businesses';
import { index as blottersIndex } from '@/routes/blotters';
import { index as cedulasIndex } from '@/routes/cedulas';
import { index as indigencyIndex } from '@/routes/indigency-certificates';
import {
    create as clearancesCreate,
    index as clearancesIndex,
} from '@/routes/clearances';
import { index as idCardsIndex } from '@/routes/id-cards';
import { index as collectionsIndex } from '@/routes/collections';
import { index as collectionReport } from '@/routes/collections/report';
import { index as accountsIndex } from '@/routes/finance/accounts';
import { index as bankAccountsIndex } from '@/routes/finance/bank-accounts';
import { index as budgetsIndex } from '@/routes/finance/budgets';
import { index as checksIndex } from '@/routes/finance/checks';
import { index as liquidationsIndex } from '@/routes/finance/liquidations';
import { index as vendorsIndex } from '@/routes/finance/vendors';
import { index as vouchersIndex } from '@/routes/finance/vouchers';
import { index as colorCategoriesIndex } from '@/routes/political-tags/categories';
import { index as formPicklistsIndex } from '@/routes/forms/picklists';
import { index as formTemplatesIndex } from '@/routes/forms/templates';
import {
    create as formTransactionsCreate,
    index as formTransactionsIndex,
} from '@/routes/forms/transactions';
import { index as savedReportsIndex } from '@/routes/reports/saved';
import { create as smsCreate, index as smsIndex } from '@/routes/sms';
import { index as smsGroupsIndex } from '@/routes/sms/groups';
import { index as smsTemplatesIndex } from '@/routes/sms/templates';
import { index as puroksIndex } from '@/routes/puroks';
import {
    businesses as businessReport,
    politicalStatus,
    residents as residentReports,
    statistics,
} from '@/routes/reports';
import {
    blankForm,
    create as residentsCreate,
    index as residentsIndex,
} from '@/routes/residents';
import { index as barangaysIndex } from '@/routes/central/barangays';
import { index as staffIndex } from '@/routes/central/staff';
import { visibleItems } from '@/lib/navigation';
import type { NavItem } from '@/types';

const DEFAULT_LOOKUP_TYPE = 'religion';

const { can, feature, barangay, isGlobalStaff } = useAccess();

const barangayItems = computed<NavItem[]>(() => [
    { title: t('nav.dashboard'), href: dashboard(), icon: LayoutGrid },
]);

const residentItems = computed(() =>
    visibleItems([
        [
            barangay.value !== null && can(Permission.ResidentsView),
            {
                title: t('nav.residents'),
                href: residentsIndex(),
                icon: UsersRound,
            },
        ],
        [
            barangay.value !== null && can(Permission.ResidentsCreate),
            {
                title: t('nav.new_resident'),
                href: residentsCreate(),
                icon: UserPlus,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ResidentsView) &&
                feature(FeatureFlag.ResidentIdCards),
            { title: t('nav.id_cards'), href: idCardsIndex(), icon: IdCard },
        ],
        [
            barangay.value !== null && can(Permission.PuroksManage),
            { title: t('nav.puroks'), href: puroksIndex(), icon: MapPinned },
        ],
        [
            barangay.value !== null &&
                can(Permission.PoliticalTagsManage) &&
                feature(FeatureFlag.PoliticalColorTagging),
            {
                title: t('nav.color_categories'),
                href: colorCategoriesIndex(),
                icon: Palette,
            },
        ],
    ]),
);

const certificateItems = computed(() =>
    visibleItems([
        [
            barangay.value !== null &&
                can(Permission.ClearancesView) &&
                feature(FeatureFlag.BarangayClearance),
            {
                title: t('nav.clearances'),
                href: clearancesIndex(),
                icon: FileCheck,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ClearancesIssue) &&
                feature(FeatureFlag.BarangayClearance),
            {
                title: t('nav.new_clearance'),
                href: clearancesCreate(),
                icon: FilePlus,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ClearancesView) &&
                feature(FeatureFlag.BusinessClearance),
            {
                title: t('nav.business_clearances'),
                href: businessClearancesIndex(),
                icon: BadgeCheck,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ClearancesIssue) &&
                feature(FeatureFlag.BusinessClearance),
            {
                title: t('nav.new_business_clearance'),
                href: businessClearancesCreate(),
                icon: FilePlus,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ClearancesView) &&
                feature(FeatureFlag.BusinessClearance),
            {
                title: t('nav.expired_businesses'),
                href: expiredBusinesses(),
                icon: CalendarX,
            },
        ],
    ]),
);

const recordItems = computed(() =>
    visibleItems([
        [
            barangay.value !== null &&
                can(Permission.CedulaView) &&
                feature(FeatureFlag.Cedula),
            { title: t('nav.cedulas'), href: cedulasIndex(), icon: Receipt },
        ],
        [
            barangay.value !== null &&
                can(Permission.ClearancesView) &&
                feature(FeatureFlag.IndigencyCertificate),
            {
                title: t('nav.indigency'),
                href: indigencyIndex(),
                icon: HandHeart,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.BlotterView) &&
                feature(FeatureFlag.Blotter),
            {
                title: t('nav.blotters'),
                href: blottersIndex(),
                icon: ShieldAlert,
            },
        ],
    ]),
);

const formItems = computed(() =>
    visibleItems([
        [
            barangay.value !== null &&
                can(Permission.FormTransactionsView) &&
                feature(FeatureFlag.FormsEngine),
            {
                title: t('nav.form_transactions'),
                href: formTransactionsIndex(),
                icon: ScrollText,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.FormTransactionsCreate) &&
                feature(FeatureFlag.FormsEngine),
            {
                title: t('nav.new_form'),
                href: formTransactionsCreate(),
                icon: FilePlus,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.FormTemplatesManage) &&
                feature(FeatureFlag.FormsEngine),
            {
                title: t('nav.form_templates'),
                href: formTemplatesIndex(),
                icon: FileStack,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.FormTemplatesManage) &&
                feature(FeatureFlag.FormsEngine) &&
                feature(FeatureFlag.FormsTablePicklists),
            {
                title: t('nav.form_picklists'),
                href: formPicklistsIndex(),
                icon: ListPlus,
            },
        ],
    ]),
);

const businessItems = computed(() =>
    visibleItems([
        [
            barangay.value !== null &&
                can(Permission.BusinessesView) &&
                feature(FeatureFlag.BusinessRegistry),
            {
                title: t('nav.businesses'),
                href: businessesIndex(),
                icon: Store,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.BusinessesManage) &&
                feature(FeatureFlag.BusinessRegistry),
            {
                title: t('nav.new_business'),
                href: businessesCreate(),
                icon: Briefcase,
            },
        ],
    ]),
);

const financeItems = computed(() =>
    visibleItems([
        [
            barangay.value !== null &&
                can(Permission.BudgetView) &&
                feature(FeatureFlag.Finance),
            { title: t('nav.budgets'), href: budgetsIndex(), icon: Wallet },
        ],
        [
            barangay.value !== null &&
                can(Permission.VouchersView) &&
                feature(FeatureFlag.Finance),
            {
                title: t('nav.vouchers'),
                href: vouchersIndex(),
                icon: ReceiptText,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ChecksView) &&
                feature(FeatureFlag.Finance),
            { title: t('nav.checks'), href: checksIndex(), icon: Banknote },
        ],
        [
            barangay.value !== null &&
                can(Permission.VouchersView) &&
                feature(FeatureFlag.Finance) &&
                feature(FeatureFlag.CashAdvanceLiquidation),
            {
                title: t('nav.liquidations'),
                href: liquidationsIndex(),
                icon: ClipboardCheck,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.BudgetView) &&
                feature(FeatureFlag.Finance),
            {
                title: t('nav.chart_of_accounts'),
                href: accountsIndex(),
                icon: BookOpen,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ChecksView) &&
                feature(FeatureFlag.Finance),
            {
                title: t('nav.bank_accounts'),
                href: bankAccountsIndex(),
                icon: Landmark,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.VouchersView) &&
                feature(FeatureFlag.Finance),
            { title: t('nav.vendors'), href: vendorsIndex(), icon: Truck },
        ],
        [
            barangay.value !== null &&
                can(Permission.CollectionsView) &&
                feature(FeatureFlag.OtherCollections),
            {
                title: t('nav.collections'),
                href: collectionsIndex(),
                icon: Coins,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.CollectionsView) &&
                feature(FeatureFlag.ConsolidatedCollectionReport),
            {
                title: t('nav.collection_report'),
                href: collectionReport(),
                icon: FileSpreadsheet,
            },
        ],
    ]),
);

const smsItems = computed(() =>
    visibleItems([
        [
            barangay.value !== null &&
                can(Permission.SmsView) &&
                feature(FeatureFlag.Sms),
            {
                title: t('nav.sms_outbox'),
                href: smsIndex(),
                icon: MessageSquare,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.SmsSend) &&
                feature(FeatureFlag.Sms),
            {
                title: t('nav.new_sms'),
                href: smsCreate(),
                icon: MessageSquarePlus,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.SmsGroupsManage) &&
                feature(FeatureFlag.Sms) &&
                feature(FeatureFlag.SmsGroups),
            {
                title: t('nav.sms_groups'),
                href: smsGroupsIndex(),
                icon: MessagesSquare,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.SmsTemplatesManage) &&
                feature(FeatureFlag.Sms) &&
                feature(FeatureFlag.SmsTemplates),
            {
                title: t('nav.sms_templates'),
                href: smsTemplatesIndex(),
                icon: NotebookText,
            },
        ],
    ]),
);

const reportItems = computed(() =>
    visibleItems([
        [
            barangay.value !== null && can(Permission.ReportsView),
            { title: t('nav.statistics'), href: statistics(), icon: BarChart3 },
        ],
        [
            barangay.value !== null && can(Permission.ReportsView),
            {
                title: t('nav.resident_reports'),
                href: residentReports(),
                icon: ClipboardList,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ReportsView) &&
                can(Permission.PoliticalTagsView) &&
                feature(FeatureFlag.PoliticalColorTagging),
            {
                title: t('nav.political_status'),
                href: politicalStatus(),
                icon: PieChart,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ReportsView) &&
                feature(FeatureFlag.BusinessRegistry),
            {
                title: t('nav.business_report'),
                href: businessReport(),
                icon: Store,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ReportsView) &&
                feature(FeatureFlag.SqlReportBuilder),
            {
                title: t('nav.saved_reports'),
                href: savedReportsIndex(),
                icon: TableProperties,
            },
        ],
        [
            barangay.value !== null &&
                can(Permission.ResidentsView) &&
                feature(FeatureFlag.BlankProfileForm),
            { title: t('nav.blank_form'), href: blankForm(), icon: FileText },
        ],
    ]),
);

const adminItems = computed(() =>
    visibleItems([
        [
            barangay.value !== null && can(Permission.UsersView),
            { title: t('nav.users'), href: usersIndex(), icon: Users },
        ],
        [
            barangay.value !== null && can(Permission.BarangayProfileManage),
            {
                title: t('nav.barangay_profile'),
                href: barangayProfileEdit(),
                icon: Settings2,
            },
        ],
        [
            can(Permission.FeaturesView),
            {
                title: t('nav.features'),
                href: featuresIndex(),
                icon: ToggleRight,
            },
        ],
        [
            can(Permission.LookupsManage),
            {
                title: t('nav.lookups'),
                href: lookupsIndex(DEFAULT_LOOKUP_TYPE),
                icon: ListChecks,
            },
        ],
        [
            can(Permission.AuditView),
            { title: t('nav.audit'), href: auditIndex(), icon: History },
        ],
    ]),
);

const centralItems = computed(() =>
    visibleItems([
        [
            can(Permission.BarangaysManage),
            {
                title: t('nav.barangays'),
                href: barangaysIndex(),
                icon: Building2,
            },
        ],
        [
            can(Permission.BarangaysManage),
            { title: t('nav.staff'), href: staffIndex(), icon: UserCog },
        ],
    ]),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <BarangaySwitcher
                v-if="isGlobalStaff && can(Permission.BarangaysSwitch)"
            />
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                :label="t('nav.section_barangay')"
                :items="barangayItems"
            />
            <NavMain
                v-if="residentItems.length"
                :label="t('nav.section_residents')"
                :items="residentItems"
            />
            <NavMain
                v-if="certificateItems.length"
                :label="t('nav.section_certificates')"
                :items="certificateItems"
            />
            <NavMain
                v-if="recordItems.length"
                :label="t('nav.section_records')"
                :items="recordItems"
            />
            <NavMain
                v-if="formItems.length"
                :label="t('nav.section_forms')"
                :items="formItems"
            />
            <NavMain
                v-if="businessItems.length"
                :label="t('nav.section_businesses')"
                :items="businessItems"
            />
            <NavMain
                v-if="financeItems.length"
                :label="t('nav.section_finance')"
                :items="financeItems"
            />
            <NavMain
                v-if="smsItems.length"
                :label="t('nav.section_sms')"
                :items="smsItems"
            />
            <NavMain
                v-if="reportItems.length"
                :label="t('nav.section_reports')"
                :items="reportItems"
            />
            <NavMain
                v-if="adminItems.length"
                :label="t('nav.section_administration')"
                :items="adminItems"
            />
            <NavMain
                v-if="centralItems.length"
                :label="t('nav.section_central')"
                :items="centralItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
