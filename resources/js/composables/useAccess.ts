import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { FeatureFlag, Permission } from '@/contracts/generated';

export function useAccess() {
    const page = usePage();

    const permissions = computed(() => new Set(page.props.auth.permissions));
    const features = computed(() => page.props.features);

    return {
        can: (permission: Permission): boolean =>
            permissions.value.has(permission),
        feature: (flag: FeatureFlag): boolean => features.value[flag] === true,
        barangay: computed(() => page.props.barangay),
        isGlobalStaff: computed(
            () => page.props.auth.user?.is_global_staff === true,
        ),
    };
}
