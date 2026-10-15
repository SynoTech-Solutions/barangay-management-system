<?php

namespace App\Enums;

enum BundleEntity: string
{
    case Lookups = 'lookups';
    case Puroks = 'puroks';
    case Users = 'users';
    case ColorCategories = 'color_categories';
    case Residents = 'residents';
    case FamilyMembers = 'family_members';
    case Educations = 'educations';
    case WorkHistories = 'work_histories';
    case IdCards = 'id_cards';
    case BarangayClearances = 'barangay_clearances';
    case IndigencyCertificates = 'indigency_certificates';
    case Businesses = 'businesses';
    case BusinessClearances = 'business_clearances';
    case Cedulas = 'cedulas';
    case Blotters = 'blotters';
    case CollectionTypes = 'collection_types';
    case OtherCollections = 'other_collections';
    case Accounts = 'accounts';
    case Budgets = 'budgets';
    case BankAccounts = 'bank_accounts';
    case Vendors = 'vendors';
    case Vouchers = 'vouchers';
    case Checks = 'checks';
    case Liquidations = 'liquidations';
    case FormPicklists = 'form_picklists';
    case FormTemplates = 'form_templates';
    case FormTransactions = 'form_transactions';
    case SmsGroups = 'sms_groups';
    case SmsTemplates = 'sms_templates';

    public const string SCHEMA_SUFFIX = '.schema.json';

    /**
     * @return list<self>
     */
    public function dependencies(): array
    {
        return match ($this) {
            self::Residents => [self::Puroks, self::ColorCategories],
            self::FamilyMembers, self::Educations, self::WorkHistories, self::IdCards, self::BarangayClearances,
            self::IndigencyCertificates, self::Cedulas, self::Blotters, self::SmsGroups => [self::Residents],
            self::Businesses => [self::Residents, self::Puroks],
            self::BusinessClearances => [self::Businesses, self::Residents],
            self::OtherCollections => [self::CollectionTypes, self::Residents],
            self::Budgets => [self::Accounts],
            self::Vouchers => [self::Budgets, self::Accounts, self::Vendors, self::Residents],
            self::Checks => [self::BankAccounts, self::Vouchers, self::Vendors],
            self::Liquidations => [self::Vouchers],
            self::FormTemplates => [self::FormPicklists],
            self::FormTransactions => [self::FormTemplates, self::Residents],
            default => [],
        };
    }

    public function schemaFile(): string
    {
        return $this->value.self::SCHEMA_SUFFIX;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $entity): string => $entity->value, self::cases());
    }
}
