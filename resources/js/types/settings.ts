export type SettingDefinition = {
    key: string;
    label: string;
    type: 'string' | 'bool' | 'int' | 'json' | 'encrypted';
    default: unknown;
    rules: string[];
    page: string | null;
};

export interface MailConfig {
    driver?: string | null;
    host?: string | null;
    port?: number | null;
    username?: string | null;
    encryption?: string | null;
    from_address?: string | null;
    from_name?: string | null;
}

export type Driver = {
    name: string;
    label: string;
    configuration_schema?: Record<
        string,
        {
            label: string;
            required?: boolean;
            type?: string;
            placeholder?: string;
            options?: Array<{ value: string; label: string }>;
        }
    >;
};

export type DynamicSettingsFormProps = {
    definitions: SettingDefinition[];
    values: Record<string, unknown>;
};

export type PaymentGatewayField = {
    label: string;
    type?: string;
    required?: boolean;
    placeholder?: string;
    options?: Array<{ value: string; label: string }>;
    secret?: boolean;
};

export type PaymentGateway = {
    key: string;
    label: string;
    configuration_schema: Record<string, PaymentGatewayField>;
    configuration: Record<string, string | number | boolean>;
    secret_fields: string[];
    status: 'configured' | 'incomplete' | 'unavailable';
    missing_fields: string[];
    error: string | null;
};

export type BillingSettingsProps = {
    gateways: PaymentGateway[];
    active_key: string | null;
    active_status: 'none' | 'active' | 'incomplete' | 'unavailable';
};
