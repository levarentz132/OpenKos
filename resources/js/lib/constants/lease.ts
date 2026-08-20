export const MOVE_OUT_REASONS = [
    { value: 'end_of_contract', label: 'End of Contract' },
    { value: 'voluntary_move', label: 'Voluntary Move' },
    { value: 'policy_violation', label: 'Policy Violation' },
    { value: 'eviction', label: 'Eviction' },
    { value: 'other', label: 'Other' },
];

export const DUE_DAY_OPTIONS = Array.from({ length: 31 }, (_, i) => {
    const day = i + 1;
    const suffix =
        day === 1 || day === 21 || day === 31
            ? 'st'
            : day === 2 || day === 22
              ? 'nd'
              : day === 3 || day === 23
                ? 'rd'
                : 'th';
    return {
        value: String(day),
        label: day === 31 ? '31st (Last day of month)' : `${day}${suffix}`,
    };
});

export const DUE_DAY_LABELS: Record<number, string> = Object.fromEntries(
    DUE_DAY_OPTIONS.map((opt) => [parseInt(opt.value, 10), opt.label]),
);

export const DEPOSIT_HANDLING_OPTIONS = [
    { value: 'carry_forward', label: 'Carry forward to new lease' },
    {
        value: 'refund_and_collect_new',
        label: 'Refund and collect new deposit',
    },
    { value: 'forfeit', label: 'Forfeit deposit' },
];
