import { useForm } from '@inertiajs/react';
import { Info, CheckCircle2, AlertCircle, ShieldCheck, Copy, Check, ExternalLink, Zap } from 'lucide-react';
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
    PaymentGatewayHealth,
    PaymentGatewaySettingsProps,
} from '@/types/settings';

const NONE = '__none__';

export default function PaymentGateway({
    gateways,
    active_key: activeKey,
    active_status: activeStatus,
    active_payment_attempt_count: activePaymentAttemptCount,
    gateway_health: gatewayHealth,
}: PaymentGatewaySettingsProps) {
    const hasActivePaymentAttempts = activePaymentAttemptCount > 0;
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
    const selectedFieldState = selectedGateway
        ? getVisibleGatewayFields(selectedGateway, selectedConfig)
        : null;
    const selectedInformationFields =
        selectedFieldState?.visibleFields.filter(
            ([, field]) => field.type === 'info',
        ) ?? [];

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
                    Configure the payment gateway used for online invoice payments and customer bookings.
                </p>
            </div>

            {/* Gateway Health & Live Status Banner */}
            {gatewayHealth && (
                <PaymentGatewayHealthCard health={gatewayHealth} />
            )}

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

            {hasActivePaymentAttempts && (
                <Alert>
                    <Info />
                    <AlertTitle>
                        Gateway changes are temporarily unavailable
                    </AlertTitle>
                    <AlertDescription>
                        {activePaymentAttemptCount} active online payment{' '}
                        {activePaymentAttemptCount === 1
                            ? 'attempt is'
                            : 'attempts are'}{' '}
                        in progress. Wait until{' '}
                        {activePaymentAttemptCount === 1 ? 'it completes' : 'they complete'}{' '}
                        or expire before switching or deactivating the gateway.
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
                    <div
                        className={
                            selectedInformationFields.length > 0
                                ? 'grid items-start gap-6 lg:grid-cols-2'
                                : undefined
                        }
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>Payment gateway</CardTitle>
                                <CardDescription>
                                    Only one installed and fully configured
                                    gateway can be active at a time.
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
                                        <SelectTrigger
                                            id="payment_gateway"
                                            disabled={hasActivePaymentAttempts}
                                        >
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
                                                        gateway.key !==
                                                            activeKey
                                                    }
                                                >
                                                    {gateway.label}
                                                    {gateway.status !==
                                                    'configured'
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
                                    selectedGateway.status !==
                                        'unavailable' && (
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

                        {selectedGateway && selectedFieldState && (
                            <div className="space-y-4">
                                {selectedInformationFields.map(
                                    ([key, field]) => (
                                        <GatewayField
                                            key={key}
                                            fieldKey={key}
                                            field={field}
                                            value={
                                                selectedFieldState
                                                    .resolvedConfiguration[
                                                    key
                                                ] ?? ''
                                            }
                                            hasSavedSecret={selectedGateway.secret_fields.includes(
                                                key,
                                            )}
                                            error={
                                                errors[`configuration.${key}`]
                                            }
                                            onChange={setConfiguration}
                                        />
                                    ),
                                )}
                                {selectedGateway.key === 'doku' && (
                                    <SandboxTrialCard gateway={selectedGateway} />
                                )}
                            </div>
                        )}
                    </div>
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
    const { fields, resolvedConfiguration, visibleFields } =
        getVisibleGatewayFields(gateway, configuration);
    const configurationFields = visibleFields.filter(
        ([, field]) => field.type !== 'info',
    );

    const renderField = ([key, field]: [string, PaymentGatewayField]) => (
        <GatewayField
            key={key}
            fieldKey={key}
            field={field}
            value={resolvedConfiguration[key] ?? ''}
            hasSavedSecret={gateway.secret_fields.includes(key)}
            error={errors[`configuration.${key}`]}
            onChange={onChange}
        />
    );

    if (fields.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                This gateway does not require additional configuration.
            </p>
        );
    }

    return (
        <div className="max-w-md space-y-4">
            {configurationFields.map(renderField)}
        </div>
    );
}

function getVisibleGatewayFields(
    gateway: PaymentGateway,
    configuration: Record<string, string>,
) {
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

    return { fields, resolvedConfiguration, visibleFields };
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
    if (field.type === 'info') {
        const resolvedUrl = field.url
            ? typeof window === 'undefined'
                ? field.url
                : new URL(field.url, window.location.origin).toString()
            : null;

        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">{field.label}</CardTitle>
                    {field.description && (
                        <CardDescription>{field.description}</CardDescription>
                    )}
                </CardHeader>
                {(field.instructions?.length || resolvedUrl || field.link) && (
                    <CardContent className="space-y-4">
                        {field.instructions?.length ? (
                            <ol className="list-decimal space-y-2 pl-5 text-sm">
                                {field.instructions.map(
                                    (instruction, index) => (
                                        <li key={index}>{instruction}</li>
                                    ),
                                )}
                            </ol>
                        ) : null}
                        {resolvedUrl && (
                            <div className="space-y-1">
                                <p className="text-sm font-medium">
                                    Webhook URL
                                </p>
                                <code className="block rounded-md border bg-muted px-3 py-2 text-xs break-all">
                                    {resolvedUrl}
                                </code>
                            </div>
                        )}
                        {field.link && (
                            <a
                                href={field.link.url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-sm font-medium text-primary underline underline-offset-4"
                            >
                                {field.link.label}
                            </a>
                        )}
                    </CardContent>
                )}
            </Card>
        );
    }

    const label = (
        <>
            {field.label}
            {field.required && <span className="text-destructive"> *</span>}
        </>
    );

    return (
        <div className="grid gap-2">
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
            {field.description && (
                <p className="text-sm text-muted-foreground">
                    {field.description}
                </p>
            )}
            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}

function PaymentGatewayHealthCard({ health }: { health: PaymentGatewayHealth }) {
    const [copied, setCopied] = useState(false);

    function copyWebhook() {
        navigator.clipboard.writeText(health.webhook_url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2500);
    }

    const isProduction = health.environment === 'production';

    return (
        <Card className={`border shadow-sm ${
            health.is_configured 
                ? isProduction
                    ? 'border-purple-500/30 bg-gradient-to-r from-purple-50/40 via-indigo-50/30 to-purple-50/40 dark:from-purple-950/20 dark:via-indigo-950/10 dark:to-purple-950/20'
                    : 'border-emerald-500/30 bg-gradient-to-r from-emerald-50/40 via-teal-50/30 to-emerald-50/40 dark:from-emerald-950/20 dark:via-teal-950/10 dark:to-emerald-950/20'
                : 'border-amber-500/30 bg-amber-50/40 dark:bg-amber-950/20'
        }`}>
            <CardHeader className="pb-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2.5">
                        <div className={`p-2 rounded-lg ${
                            health.is_configured
                                ? isProduction
                                    ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300'
                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300'
                        }`}>
                            <ShieldCheck className="size-5" />
                        </div>
                        <div>
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <span>Status Gateway Pembayaran</span>
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                    <span className={`size-2 rounded-full animate-pulse ${
                                        health.is_configured ? (isProduction ? 'bg-purple-500' : 'bg-emerald-500') : 'bg-amber-500'
                                    }`} />
                                    {health.is_configured 
                                        ? (isProduction ? 'AKTIF (LIVE PRODUCTION)' : 'AKTIF (SANDBOX SIMULATION)') 
                                        : 'BELUM TERKONFIGURASI'
                                    }
                                </span>
                            </CardTitle>
                            <CardDescription className="text-xs mt-0.5">
                                {health.is_configured
                                    ? isProduction
                                        ? 'Gateway terhubung ke DOKU Live Production. Tamu dapat melakukan pembayaran riil.'
                                        : 'Gateway terhubung ke DOKU Sandbox mode. Pembayaran dalam mode simulasi.'
                                    : 'Kredensial DOKU (Client ID & Secret Key) belum lengkap di file .env server.'
                                }
                            </CardDescription>
                        </div>
                    </div>
                    <span className={`text-xs font-mono font-bold px-3 py-1 rounded-md uppercase tracking-wider ${
                        isProduction
                            ? 'bg-purple-600 text-white shadow-sm'
                            : 'bg-emerald-600 text-white shadow-sm'
                    }`}>
                        {health.environment}
                    </span>
                </div>
            </CardHeader>
            <CardContent className="space-y-3 pt-0">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                    <div className="p-2.5 bg-white/80 dark:bg-zinc-900/80 rounded-lg border border-zinc-200/80 dark:border-zinc-800">
                        <span className="text-muted-foreground block text-[11px] mb-1">API Endpoint:</span>
                        <span className="font-mono font-semibold text-zinc-900 dark:text-zinc-100 block truncate" title={health.endpoint}>
                            {health.endpoint}
                        </span>
                    </div>
                    <div className="p-2.5 bg-white/80 dark:bg-zinc-900/80 rounded-lg border border-zinc-200/80 dark:border-zinc-800">
                        <span className="text-muted-foreground block text-[11px] mb-1">Client ID:</span>
                        <span className="font-mono font-semibold text-zinc-900 dark:text-zinc-100 block truncate">
                            {health.client_id_masked || '(Belum diset)'}
                        </span>
                    </div>
                    <div className="p-2.5 bg-white/80 dark:bg-zinc-900/80 rounded-lg border border-zinc-200/80 dark:border-zinc-800">
                        <span className="text-muted-foreground block text-[11px] mb-1">Secret Key Status:</span>
                        <span className={`font-semibold inline-flex items-center gap-1 ${
                            health.secret_key_set ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'
                        }`}>
                            {health.secret_key_set ? <CheckCircle2 className="size-3.5" /> : <AlertCircle className="size-3.5" />}
                            {health.secret_key_set ? 'Terkonfigurasi' : 'Belum Terisi'}
                        </span>
                    </div>
                    <div className="p-2.5 bg-white/80 dark:bg-zinc-900/80 rounded-lg border border-zinc-200/80 dark:border-zinc-800">
                        <span className="text-muted-foreground block text-[11px] mb-1">Callback URL:</span>
                        <span className="font-mono font-semibold text-zinc-900 dark:text-zinc-100 block truncate" title={health.callback_url}>
                            {health.callback_url}
                        </span>
                    </div>
                </div>

                {/* Webhook notification URL box with copy */}
                <div className="p-2.5 bg-white/90 dark:bg-zinc-900/90 rounded-lg border border-zinc-200/80 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div className="flex items-center gap-2 min-w-0">
                        <span className="font-medium text-zinc-700 dark:text-zinc-300 shrink-0">DOKU Webhook / Notification URL:</span>
                        <code className="font-mono text-xs bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded text-zinc-800 dark:text-zinc-200 truncate">
                            {health.webhook_url}
                        </code>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={copyWebhook}
                        className="h-7 text-xs gap-1.5 shrink-0"
                    >
                        {copied ? (
                            <>
                                <Check className="size-3 text-emerald-600" />
                                <span className="text-emerald-600 font-medium">Tersalin!</span>
                            </>
                        ) : (
                            <>
                                <Copy className="size-3" />
                                <span>Salin URL Webhook</span>
                            </>
                        )}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function SandboxTrialCard({ gateway }: { gateway: PaymentGateway }) {
    const [amount, setAmount] = useState('10000');
    const [loadingTrial, setLoadingTrial] = useState(false);
    const [trialData, setTrialData] = useState<{ checkout_url: string; reference: string; amount: number; environment?: string } | null>(null);
    const [trialError, setTrialError] = useState<string | null>(null);

    const [loadingSim, setLoadingSim] = useState(false);
    const [simMessage, setSimMessage] = useState<string | null>(null);

    async function handleCreateTrial() {
        setLoadingTrial(true);
        setTrialError(null);
        try {
            const res = await fetch('/api/v1/sandbox/trial', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ amount: Number(amount) || 10000 }),
            });
            const result = await res.json();
            if (result.success && result.checkout_url) {
                setTrialData(result);
            } else {
                setTrialError(result.message || 'Gagal membuat sesi checkout test.');
            }
        } catch (e: any) {
            setTrialError(e.message || 'Terjadi kesalahan jaringan.');
        } finally {
            setLoadingTrial(false);
        }
    }

    async function handleSimulateWebhook() {
        setLoadingSim(true);
        setSimMessage(null);
        try {
            const res = await fetch('/api/v1/sandbox/simulate-payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    reference: trialData?.reference,
                }),
            });
            const result = await res.json();
            if (result.success) {
                setSimMessage(result.message);
            } else {
                setSimMessage('Simulasi gagal: ' + result.message);
            }
        } catch (e: any) {
            setSimMessage('Kesalahan jaringan: ' + e.message);
        } finally {
            setLoadingSim(false);
        }
    }

    const isProd = trialData?.environment === 'production';

    return (
        <Card className="border-indigo-500/30 bg-indigo-50/20 dark:bg-indigo-950/20 shadow-sm">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base font-semibold text-indigo-950 dark:text-indigo-200 flex items-center gap-2">
                        <Zap className="size-4 text-indigo-600 dark:text-indigo-400" />
                        <span>Pengujian Checkout DOKU</span>
                    </CardTitle>
                    <span className="text-[11px] font-mono font-bold px-2.5 py-0.5 rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300">
                        LIVE TEST
                    </span>
                </div>
                <CardDescription className="text-xs">
                    Tombol ini otomatis mengikuti mode environment di server (<strong>Production</strong> untuk link checkout riil DOKU, atau <strong>Sandbox</strong> untuk link simulasi).
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="trial_amount" className="text-xs font-medium">Nominal Test (IDR)</Label>
                    <div className="flex gap-2">
                        <Input
                            id="trial_amount"
                            type="number"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            className="bg-white dark:bg-zinc-900 text-sm h-9"
                            placeholder="10000"
                        />
                        <Button
                            type="button"
                            onClick={handleCreateTrial}
                            disabled={loadingTrial}
                            className="h-9 px-3.5 bg-indigo-600 hover:bg-indigo-700 text-white shrink-0 text-xs font-medium gap-1.5"
                        >
                            {loadingTrial ? 'Menghubungkan...' : 'Generate Test Link'}
                        </Button>
                    </div>
                </div>

                {trialError && (
                    <p className="text-xs text-red-600 bg-red-50 dark:bg-red-950/40 p-2.5 rounded-lg border border-red-200">
                        {trialError}
                    </p>
                )}

                {trialData && (
                    <div className="p-3.5 bg-white dark:bg-zinc-900 rounded-lg border space-y-3 shadow-xs">
                        <div className="flex justify-between items-center text-xs">
                            <span className="text-muted-foreground">Nomor Invoice:</span>
                            <span className="font-mono font-bold text-zinc-900 dark:text-zinc-100">{trialData.reference}</span>
                        </div>
                        <div className="flex justify-between items-center text-xs">
                            <span className="text-muted-foreground">Nominal:</span>
                            <span className="font-semibold text-emerald-600 dark:text-emerald-400">Rp {Number(trialData.amount).toLocaleString('id-ID')}</span>
                        </div>
                        <div className="flex justify-between items-center text-xs">
                            <span className="text-muted-foreground">Mode Endpoint:</span>
                            <span className="font-mono uppercase font-bold text-indigo-600 dark:text-indigo-400">
                                {trialData.environment || 'DOKU'}
                            </span>
                        </div>
                        <div className="pt-2 flex flex-wrap gap-2">
                            <a
                                href={trialData.checkout_url}
                                target="_blank"
                                rel="noreferrer"
                                className="flex-1 text-center py-2 px-3 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition flex items-center justify-center gap-1.5 shadow-xs"
                            >
                                <span>Buka Halaman DOKU Checkout</span>
                                <ExternalLink className="size-3.5" />
                            </a>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleSimulateWebhook}
                                disabled={loadingSim}
                                className="h-8.5 text-xs shrink-0 gap-1"
                            >
                                {loadingSim ? 'Memproses...' : 'Simulasi Lunas Webhook ⚡'}
                            </Button>
                        </div>
                    </div>
                )}

                {simMessage && (
                    <Alert className="bg-emerald-100/80 border-emerald-300 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200">
                        <AlertTitle className="text-xs font-bold">Hasil Simulasi</AlertTitle>
                        <AlertDescription className="text-xs">{simMessage}</AlertDescription>
                    </Alert>
                )}
            </CardContent>
        </Card>
    );
}
