import type { ReportFilters } from '@/types';

export type ReportQuery = Record<string, string | number | string[] | number[]>;

export function reportQuery(filters: ReportFilters): ReportQuery {
    const entries = Object.entries({
        ...filters,
        voter: filters.voter === null ? null : filters.voter ? 'yes' : 'no',
        include_incomplete: filters.include_incomplete ? 1 : null,
        removed: null,
        flags: filters.flags.length ? filters.flags : null,
        attribute_values: filters.attribute_values.length
            ? filters.attribute_values
            : null,
    }).filter(([, value]) => value !== null && value !== '');

    return Object.fromEntries(entries) as ReportQuery;
}
