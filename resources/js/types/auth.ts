export type User = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    designation: string | null;
    is_global_staff: boolean;
    two_factor_enabled: boolean;
    avatar?: string;
};

export type Auth = {
    user: User;
    permissions: string[];
};

export type CurrentBarangay = {
    id: number;
    name: string;
    psgc_code: string;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
