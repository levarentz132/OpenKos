import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SegmentedToggle } from '@/components/ui/segmented-toggle';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update as updatePaymentGateway } from '@/routes/settings/payment-gateway';
import type {
    PaymentGateway,
    PaymentGatewayField,
    PaymentGatewaySettingsProps,
} from '@/types/settings';

const NONE = '__none__';

export default function PaymentGateway({
    gateways,
    active_key: activeKey,
    active_status: activeStatus,
}: PaymentGatewaySettingsProps) {
    const initialKey = gateways.some((gateway) => gateway.key === activeKey)
        ? activeKey!
        : NONE;
    const [configs, setConfigs] = useState<
        Record<string, Record<string, string>>
    >(
        Object.fromEntries(
            gateways.map((gateway) => [
                gateway.key,
                Object.fromEntries(
                    Object.entries(gateway.configuration).map(
                        ([key, value]) => [key, String(value)],
                    ),
                ),
            ]),
        ),
    );
    const { data, setData, transform, submit, processing, errors } = useForm({
        gateway: initialKey,
        configuration: configs[initialKey] ?? {},
    });

    const selectedGateway = gateways.find(
        (gateway) => gateway.key === data.gateway,
    );
    const selectedConfig = data.configuration;

    function selectGateway(key: string) {
        const configuration = configs[key] ?? {};

        setData({ gateway: key, configuration });
    }

    function setConfiguration(key: string, value: string) {
        const configuration = { ...selectedConfig, [key]: value };

        setConfigs((current) => ({
            ...current,
            [data.gateway]: configuration,
        }));
        setData('configuration', configuration);
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        transform((form) => ({
            ...form,
            gateway: form.gateway === NONE ? null : form.gateway,
        }));
        submit(updatePaymentGateway());
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">Payment Gateway</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Configure the payment gateway used for online invoice
                    payments.
                </p>
            </div>

            {activeStatus === 'unavailable' && (
                <Alert variant="destructive">
                    <AlertTitle>Payment gateway unavailable</AlertTitle>
                    <AlertDescription>
                        The configured gateway ({activeKey}) is not currently
                        installed or could not be loaded. Select another gateway
                        to recover.
                    </AlertDescription>
                </Alert>
            )}

            {activeStatus === 'incomplete' && (
                <Alert>
                    <AlertTitle>Payment gateway needs configuration</AlertTitle>
                    <AlertDescription>
                        Complete the required fields before online payments can
                        use the active gateway.
                    </AlertDescription>
                </Alert>
            )}

            {gateways.length === 0 ? (
                <Alert>
                    <AlertTitle>No payment gateways installed</AlertTitle>
                    <AlertDescription>
                        Install a payment gateway plugin before activating
                        online invoice payments.
                    </AlertDescription>
                </Alert>
            ) : (
                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment gateway</CardTitle>
                            <CardDescription>
                                Only one installed and fully configured gateway
                                can be active at a time.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid max-w-md gap-2">
                                <Label htmlFor="payment_gateway">
                                    Active gateway
                                </Label>
                                <Select
                                    value={data.gateway}
                                    onValueChange={selectGateway}
                                >
                                    <SelectTrigger id="payment_gateway">
                                        <SelectValue placeholder="Select a gateway" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>
                                            No active gateway
                                        </SelectItem>
                                        {gateways.map((gateway) => (
                                            <SelectItem
                                                key={gateway.key}
                                                value={gateway.key}
                                                disabled={
                                                    gateway.status ===
                                                        'unavailable' &&
                                                    gateway.key !== activeKey
                                                }
                                            >
                                                {gateway.label}
                                                {gateway.status !== 'configured'
                                                    ? ` (${gateway.status})`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.gateway && (
                                    <p className="text-sm text-red-600">
                                        {errors.gateway}
                                    </p>
                                )}
                            </div>

                            {selectedGateway?.status === 'unavailable' && (
                                <Alert variant="destructive">
                                    <AlertTitle>
                                        {selectedGateway.label}
                                    </AlertTitle>
                                    <AlertDescription>
                                        {selectedGateway.error}
                                    </AlertDescription>
                                </Alert>
                            )}

                            {selectedGateway &&
                                selectedGateway.status !== 'unavailable' && (
                                    <GatewayConfiguration
                                        gateway={selectedGateway}
                                        configuration={selectedConfig}
                                        errors={errors}
                                        onChange={setConfiguration}
                                    />
                                )}
                        </CardContent>
                        <CardFooter>
                            <Button disabled={processing}>Save</Button>
                        </CardFooter>
                    </Card>
                </form>
            )}
        </div>
    );
}

function GatewayConfiguration({
    gateway,
    configuration,
    errors,
    onChange,
}: {
    gateway: PaymentGateway;
    configuration: Record<string, string>;
    errors: Record<string, string>;
    onChange: (key: string, value: string) => void;
}) {
    const fields = Object.entries(gateway.configuration_schema);
    const resolvedConfiguration = Object.fromEntries(
        fields.map(([key, field]) => [
            key,
            configuration[key] ??
                (field.default === undefined ? '' : String(field.default)),
        ]),
    );
    const visibleFields = fields.filter(([, field]) => {
        const condition = field.visible_when;

        return (
            condition === undefined ||
            resolvedConfiguration[condition.field] === condition.value
        );
    });

    if (fields.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                This gateway does not require additional configuration.
            </p>
        );
    }

    return (
        <div className="space-y-4">
            {visibleFields.map(([key, field]) => (
                <GatewayField
                    key={key}
                    fieldKey={key}
                    field={field}
                    value={resolvedConfiguration[key] ?? ''}
                    hasSavedSecret={gateway.secret_fields.includes(key)}
                    error={errors[`configuration.${key}`]}
                    onChange={onChange}
                />
            ))}
        </div>
    );
}

function GatewayField({
    fieldKey,
    field,
    value,
    hasSavedSecret,
    error,
    onChange,
}: {
    fieldKey: string;
    field: PaymentGatewayField;
    value: string;
    hasSavedSecret: boolean;
    error?: string;
    onChange: (key: string, value: string) => void;
}) {
    const label = (
        <>
            {field.label}
            {field.required && <span className="text-destructive"> *</span>}
        </>
    );

    return (
        <div className="grid max-w-md gap-2">
            <Label
                htmlFor={
                    field.presentation === 'segmented'
                        ? undefined
                        : `payment_gateway_${fieldKey}`
                }
            >
                {label}
            </Label>
            {field.presentation === 'segmented' && field.options ? (
                <SegmentedToggle
                    ariaLabel={field.label}
                    className="max-w-md"
                    options={field.options}
                    value={value}
                    onChange={(next) => onChange(fieldKey, next)}
                />
            ) : field.type === 'select' && field.options ? (
                <Select
                    value={value}
                    onValueChange={(next) => onChange(fieldKey, next)}
                >
                    <SelectTrigger id={`payment_gateway_${fieldKey}`}>
                        <SelectValue
                            placeholder={field.placeholder ?? 'Select...'}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {field.options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : (
                <Input
                    id={`payment_gateway_${fieldKey}`}
                    type={
                        field.type === 'password' || field.secret
                            ? 'password'
                            : field.type === 'number'
                              ? 'number'
                              : 'text'
                    }
                    value={value}
                    onChange={(event) => onChange(fieldKey, event.target.value)}
                    placeholder={
                        hasSavedSecret ? '••••••••••••' : field.placeholder
                    }
                />
            )}
            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}
