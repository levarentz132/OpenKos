export type AppAccessStatus =
    | 'active'
    | 'invited'
    | 'email_only'
    | 'disabled'
    | 'none';

type AccessUser =
    | {
          is_active: boolean;
          email_verified_at: string | null;
          invited_at: string | null;
      }
    | null
    | undefined;

type AccessTenant =
    | {
          email?: string | null;
          email_verified_at?: string | null;
          phone_verified_at?: string | null;
          last_login_at?: string | null;
          google_id?: string | null;
          is_active?: boolean;
      }
    | null
    | undefined;

/**
 * Derives a tenant's app-access state from its linked user or direct tenant auth fields.
 * - none: no linked user and no direct tenant login/email
 * - active: can sign in (activated + verified or logged in via email/Google/OTP)
 * - invited: invitation sent, not yet accepted
 * - disabled: previously activated, access revoked (still receives notifications)
 * - email_only: email saved for notifications, never invited
 */
export function appAccessStatus(
    user: AccessUser,
    tenant?: AccessTenant,
): AppAccessStatus {
    if (!user) {
        if (tenant) {
            const hasDirectAuth = Boolean(
                tenant.email_verified_at ||
                    tenant.phone_verified_at ||
                    tenant.last_login_at ||
                    tenant.google_id,
            );
            if (hasDirectAuth) {
                return tenant.is_active === false ? 'disabled' : 'active';
            }
            if (tenant.email) {
                return 'email_only';
            }
        }
        return 'none';
    }

    if (user.is_active && user.email_verified_at) {
        return 'active';
    }

    if (user.invited_at) {
        return 'invited';
    }

    if (user.email_verified_at) {
        return 'disabled';
    }

    return 'email_only';
}

/**
 * Label for the "send an activation link" action, matching the App Access section.
 * Returns null for `none` (that state uses the "Invite to App" modal instead).
 */
export function inviteActionLabel(status: AppAccessStatus): string | null {
    switch (status) {
        case 'email_only':
            return 'Send Invitation';
        case 'invited':
        case 'active':
            return 'Resend Invitation';
        case 'disabled':
            return 'Re-invite';
        default:
            return null;
    }
}
