import type { NavItem } from '@/types';

export function visibleItems(entries: Array<[boolean, NavItem]>): NavItem[] {
    return entries.filter(([visible]) => visible).map(([, item]) => item);
}
