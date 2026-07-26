# Graph Report - js (2026-07-26)

## Corpus Check

- 224 files · ~69,243 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary

- 839 nodes · 3249 edges · 41 communities (37 shown, 4 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 10 edges (avg confidence: 0.53)
- Token cost: 0 input · 0 output

## Graph Freshness

- Built from commit: `d44ff42b`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)

- button.tsx
- tenant-documents-sheet.tsx
- cn
- models.ts
- index.tsx
- index.tsx
- use-appearance.tsx
- app-header.tsx
- sidebar.tsx
- status-badge.tsx
- app-sidebar.tsx
- index.tsx
- app-sidebar-layout.tsx
- formatPrice
- index.ts
- formatDate
- index.tsx
- lease-detail-sheet.tsx
- utils.ts
- dashboard.tsx
- index.tsx
- Heading
- index.ts
- rent.tsx
- toggle-group.tsx
- index.tsx
- formatters.ts
- settings.ts
- index.tsx
- useIsMobile
- table.ts
- sort-header.tsx
- index.tsx
- icon.tsx
- placeholder-pattern.tsx
- index.ts
- index.tsx
- dropdown-menu.tsx
- index.tsx
- documents.tsx

## God Nodes (most connected - your core abstractions)

1. `cn()` - 171 edges
2. `Button()` - 82 edges
3. `formatDate()` - 70 edges
4. `formatPrice()` - 63 edges
5. `StatusBadge()` - 39 edges
6. `Input()` - 39 edges
7. `Label()` - 36 edges
8. `InputError()` - 33 edges
9. `SheetContent()` - 31 edges
10. `SheetHeader()` - 31 edges

## Surprising Connections (you probably didn't know these)

- `TicketDetailSheet()` --calls--> `formatDate()` [EXTRACTED]
  components/features/maintenance/ticket-detail-sheet.tsx → lib/formatters.ts
- `LeaseContextCard()` --calls--> `formatDate()` [EXTRACTED]
  components/features/tenant-portal/lease-context.tsx → lib/formatters.ts
- `LeaseOptions()` --calls--> `formatDate()` [EXTRACTED]
  components/features/tenant-portal/lease-context.tsx → lib/formatters.ts
- `LeaseOptionGroup()` --calls--> `formatDate()` [EXTRACTED]
  components/features/tenant-portal/lease-context.tsx → lib/formatters.ts
- `UnitOverview()` --calls--> `formatPrice()` [EXTRACTED]
  components/features/units/unit-overview.tsx → lib/formatters.ts

## Import Cycles

- 3-file cycle: `components/features/index.ts -> components/features/leases/index.ts -> components/features/leases/lease-detail-sheet.tsx -> components/features/index.ts`

## Communities (41 total, 4 thin omitted)

### Community 0 - "button.tsx"

Cohesion: 0.07
Nodes (61): DataTablePaginationProps, Props, Props, Props, MoveOutSheet(), REASONS, DEPOSIT_HANDLING, formatUnitOption() (+53 more)

### Community 1 - "tenant-documents-sheet.tsx"

Cohesion: 0.17
Nodes (17): Props, priorityLabel, TicketDetailSheet(), InviteToAppSheet(), TenantAppAccessSection(), TenantWithUser, TYPE_LABELS, Dialog() (+9 more)

### Community 2 - "cn"

Cohesion: 0.07
Nodes (41): PasswordInput(), ComboboxChip(), ComboboxChips(), ComboboxChipsInput(), ComboboxClear(), ComboboxContent(), ComboboxEmpty(), ComboboxGroup() (+33 more)

### Community 3 - "models.ts"

Cohesion: 0.06
Nodes (41): ActivityFeedItem(), getActivitySummaryChips(), parseActivity(), BusinessHealthPanel(), getGreeting(), OperationalBriefingCard(), PropertyOverviewCard(), PropertyFormSheet() (+33 more)

### Community 4 - "index.tsx"

Cohesion: 0.16
Nodes (22): DataTable(), SearchInput(), SearchInputProps, MoveUnitSheet(), Heading(), DropdownMenu(), DropdownMenuContent(), DropdownMenuItem() (+14 more)

### Community 5 - "index.tsx"

Cohesion: 0.19
Nodes (12): DataTableProps, TableColumn, DataTablePagination(), WorkspaceTable(), columns, columns, columns, priorityColors (+4 more)

### Community 6 - "use-appearance.tsx"

Cohesion: 0.08
Nodes (28): appName, AppearanceToggleTab(), NotificationSetupBanner(), Toaster(), Appearance, applyTheme(), getStoredAppearance(), handleSystemThemeChange() (+20 more)

### Community 7 - "app-header.tsx"

Cohesion: 0.13
Nodes (20): AppHeader(), mainNavItems, Props, UserInfo(), Props, UserMenuContent(), Avatar(), AvatarFallback() (+12 more)

### Community 8 - "sidebar.tsx"

Cohesion: 0.10
Nodes (36): AppLogo(), NavFooter(), NavMain(), NavUser(), Sidebar(), SidebarContent(), SidebarContext, SidebarFooter() (+28 more)

### Community 9 - "status-badge.tsx"

Cohesion: 0.18
Nodes (7): EntityWorkspaceLayout(), PluginRegion(), regions, WorkspaceTabDef, WorkspaceTabs(), MaintenanceTicket, WorkspaceUser

### Community 10 - "app-sidebar.tsx"

Cohesion: 0.20
Nodes (15): AppSidebar(), canSee(), canSeePlatformPage(), iconMap, platformNavItems(), platformPageNavItems(), usePlatformTabs(), Auth (+7 more)

### Community 11 - "index.tsx"

Cohesion: 0.13
Nodes (18): CountryCode, countryCodes, parseE164(), sorted, PhoneInput(), Option, SearchableSelectProps, Command() (+10 more)

### Community 12 - "app-sidebar-layout.tsx"

Cohesion: 0.15
Nodes (10): AppContent(), Props, AppShell(), Props, SidebarInset(), SidebarProvider(), BreadcrumbItem, AppLayoutProps (+2 more)

### Community 13 - "formatPrice"

Cohesion: 0.19
Nodes (14): LeaseEditSheet(), InvoiceActionItem(), InvoiceDetailSheet(), PaymentDetailSheet(), QueuePaymentSheet(), SubmitPortalPaymentForm(), UnitDetailSheet(), formatPeriod() (+6 more)

### Community 15 - "formatDate"

Cohesion: 0.18
Nodes (6): TenantDocumentsSheet(), formatSize(), LeaseSection(), Props, ShowTicket(), Ticket

### Community 16 - "index.tsx"

Cohesion: 0.15
Nodes (10): LeaseContextCard(), LeaseOptionGroup(), LeaseOptions(), TenantLeaseContext(), AccountSummary(), PaymentRow(), PortalPayment, PortalPayment (+2 more)

### Community 17 - "lease-detail-sheet.tsx"

Cohesion: 0.16
Nodes (15): LeaseDetailSheet(), LeaseOverview(), RecordPaymentForm(), Collapsible(), CollapsibleContent(), CollapsibleTrigger(), BILLING_STRATEGIES, BILLING_UNITS (+7 more)

### Community 18 - "utils.ts"

Cohesion: 0.20
Nodes (10): Props, Separator(), Skeleton(), IsCurrentOrParentUrlFn, IsCurrentUrlFn, useCurrentUrl(), UseCurrentUrlReturn, WhenCurrentUrlFn (+2 more)

### Community 19 - "dashboard.tsx"

Cohesion: 0.16
Nodes (13): formatDate(), AccountSummary, AccountSummaryCard(), ActiveStayCard(), activityLabel(), activitySupport(), CurrentPaymentCard(), Lease (+5 more)

### Community 20 - "index.tsx"

Cohesion: 0.24
Nodes (10): AppSidebarHeader(), Breadcrumbs(), Breadcrumb(), BreadcrumbEllipsis(), BreadcrumbItem(), BreadcrumbLink(), BreadcrumbList(), BreadcrumbPage() (+2 more)

### Community 21 - "Heading"

Cohesion: 0.20
Nodes (6): PermissionEntry, PermissionGroup, RoleData, RoleFormData, RolePair, WorkspaceRole

### Community 22 - "index.ts"

Cohesion: 0.39
Nodes (3): TenantLayout(), columns, WorkspaceTenant

### Community 23 - "rent.tsx"

Cohesion: 0.11
Nodes (17): UnitLayout(), columns, LeaseData, LeaseInfo, ManagedProperty, PaymentAllocation, PropertyRef, PropertyTypeOption (+9 more)

### Community 24 - "toggle-group.tsx"

Cohesion: 0.24
Nodes (9): SearchableSelect(), useComboboxAnchor(), SidebarMenuSkeleton(), ToggleGroup(), ToggleGroupContext, ToggleGroupItem(), Toggle(), toggleVariants (+1 more)

### Community 25 - "index.tsx"

Cohesion: 0.22
Nodes (8): formatDate(), Index(), ManagedUser, PageProps, Property, RoleOption, UserDetailSheet(), UserRole

### Community 26 - "formatters.ts"

Cohesion: 0.13
Nodes (11): Props, ManageTwoFactor(), Props, PasskeyRegistration(), InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot (+3 more)

### Community 27 - "settings.ts"

Cohesion: 0.29
Nodes (5): DynamicSettingsForm(), Driver, DynamicSettingsFormProps, MailConfig, SettingDefinition

### Community 28 - "index.tsx"

Cohesion: 0.48
Nodes (5): AlertError(), Alert(), AlertDescription(), AlertTitle(), alertVariants

### Community 29 - "useIsMobile"

Cohesion: 0.17
Nodes (10): TenantOverview(), UnitOverview(), STATUS_CONFIGS, StatusBadge(), StatusConfig, Index(), PageProps, InvoiceDetail() (+2 more)

### Community 30 - "table.ts"

Cohesion: 0.50
Nodes (3): PaginationLinks, TableColumnMeta, TableFilterOption

### Community 31 - "sort-header.tsx"

Cohesion: 0.67
Nodes (3): SortHeader(), SortHeaderProps, sortPart()

### Community 36 - "index.ts"

Cohesion: 0.15
Nodes (6): Props, TwoFactorSetupStep(), CopiedValue, CopyFn, useClipboard(), UseClipboardReturn

### Community 37 - "index.tsx"

Cohesion: 0.24
Nodes (10): FilterBar(), FilterBarProps, optLabel(), SelectFilter(), registerRegion(), Badge(), badgeVariants, priorityColors (+2 more)

### Community 38 - "dropdown-menu.tsx"

Cohesion: 0.18
Nodes (7): DropdownMenuCheckboxItem(), DropdownMenuGroup(), DropdownMenuLabel(), DropdownMenuRadioItem(), DropdownMenuShortcut(), DropdownMenuSubContent(), DropdownMenuSubTrigger()

### Community 39 - "index.tsx"

Cohesion: 0.31
Nodes (8): TenantDetailSheet(), TenantFormSheet(), AccessUser, appAccessStatus, inviteActionLabel(), Index(), PageProps, AvailableUnit

### Community 40 - "documents.tsx"

Cohesion: 0.31
Nodes (4): columns, LeaseLayout(), ProofRow, WorkspaceLease

## Knowledge Gaps

- **135 isolated node(s):** `appName`, `FilterBarProps`, `DataTableProps`, `DataTablePaginationProps`, `SearchInputProps` (+130 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **4 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions

_Questions this graph is uniquely positioned to answer:_

- **Why does `cn()` connect `cn` to `button.tsx`, `tenant-documents-sheet.tsx`, `models.ts`, `index.tsx`, `index.tsx`, `use-appearance.tsx`, `app-header.tsx`, `dropdown-menu.tsx`, `sidebar.tsx`, `index.tsx`, `app-sidebar-layout.tsx`, `utils.ts`, `index.tsx`, `toggle-group.tsx`, `formatters.ts`, `index.tsx`?**
  _High betweenness centrality (0.209) - this node is a cross-community bridge._
- **Why does `Button()` connect `button.tsx` to `tenant-documents-sheet.tsx`, `cn`, `models.ts`, `index.tsx`, `index.tsx`, `use-appearance.tsx`, `app-header.tsx`, `sidebar.tsx`, `status-badge.tsx`, `index.tsx`, `formatPrice`, `index.tsx`, `lease-detail-sheet.tsx`, `utils.ts`, `dashboard.tsx`, `index.tsx`, `formatters.ts`, `useIsMobile`, `index.ts`, `index.tsx`, `index.tsx`?**
  _High betweenness centrality (0.094) - this node is a cross-community bridge._
- **Why does `formatDate()` connect `dashboard.tsx` to `button.tsx`, `tenant-documents-sheet.tsx`, `models.ts`, `index.tsx`, `index.tsx`, `index.tsx`, `index.tsx`, `documents.tsx`, `formatPrice`, `formatDate`, `index.tsx`, `lease-detail-sheet.tsx`, `index.ts`, `rent.tsx`, `useIsMobile`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._
- **What connects `appName`, `FilterBarProps`, `DataTableProps` to the rest of the system?**
  _135 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `button.tsx` be split into smaller, more focused modules?**
  _Cohesion score 0.06978417266187051 - nodes in this community are weakly interconnected._
- **Should `cn` be split into smaller, more focused modules?**
  _Cohesion score 0.07446808510638298 - nodes in this community are weakly interconnected._
- **Should `models.ts` be split into smaller, more focused modules?**
  _Cohesion score 0.06448979591836734 - nodes in this community are weakly interconnected._
