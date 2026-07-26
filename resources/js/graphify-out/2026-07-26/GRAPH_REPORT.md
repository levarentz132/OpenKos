# Graph Report - js (2026-07-26)

## Corpus Check

- 224 files · ~69,156 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary

- 836 nodes · 3243 edges · 36 communities (32 shown, 4 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 7 edges (avg confidence: 0.54)
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
- `BreadcrumbEllipsis()` --calls--> `cn()` [EXTRACTED]
  components/ui/breadcrumb.tsx → lib/utils.ts
- `CommandSeparator()` --calls--> `cn()` [EXTRACTED]
  components/ui/command.tsx → lib/utils.ts
- `CommandShortcut()` --calls--> `cn()` [EXTRACTED]
  components/ui/command.tsx → lib/utils.ts

## Import Cycles

- 3-file cycle: `components/features/index.ts -> components/features/leases/index.ts -> components/features/leases/lease-detail-sheet.tsx -> components/features/index.ts`

## Communities (36 total, 4 thin omitted)

### Community 0 - "button.tsx"

Cohesion: 0.07
Nodes (61): DataTablePaginationProps, Props, Props, Props, MoveOutSheet(), REASONS, DEPOSIT_HANDLING, formatUnitOption() (+53 more)

### Community 1 - "tenant-documents-sheet.tsx"

Cohesion: 0.06
Nodes (42): Props, ManageTwoFactor(), Props, Props, PasskeyRegistration(), Props, TwoFactorSetupStep(), priorityLabel (+34 more)

### Community 2 - "cn"

Cohesion: 0.06
Nodes (48): PasswordInput(), Props, TextLink(), ComboboxChip(), ComboboxChips(), ComboboxChipsInput(), ComboboxClear(), ComboboxContent() (+40 more)

### Community 3 - "models.ts"

Cohesion: 0.05
Nodes (46): ActivityFeedItem(), getActivitySummaryChips(), parseActivity(), BusinessHealthPanel(), getGreeting(), OperationalBriefingCard(), PropertyOverviewCard(), PropertyFormSheet() (+38 more)

### Community 4 - "index.tsx"

Cohesion: 0.15
Nodes (27): FilterBar(), FilterBarProps, optLabel(), SelectFilter(), DataTable(), SearchInput(), SearchInputProps, MoveUnitSheet() (+19 more)

### Community 5 - "index.tsx"

Cohesion: 0.14
Nodes (20): DataTableProps, TableColumn, DataTablePagination(), PluginRegion(), regions, registerRegion(), WorkspaceTable(), columns (+12 more)

### Community 6 - "use-appearance.tsx"

Cohesion: 0.10
Nodes (21): appName, AppearanceToggleTab(), Toaster(), Appearance, applyTheme(), getStoredAppearance(), handleSystemThemeChange(), initializeTheme() (+13 more)

### Community 7 - "app-header.tsx"

Cohesion: 0.14
Nodes (21): AppHeader(), mainNavItems, Props, AppLogo(), NavUser(), UserInfo(), Props, UserMenuContent() (+13 more)

### Community 8 - "sidebar.tsx"

Cohesion: 0.11
Nodes (26): NavFooter(), NavMain(), Sidebar(), SidebarContext, SidebarGroup(), SidebarGroupAction(), SidebarGroupContent(), SidebarGroupLabel() (+18 more)

### Community 9 - "status-badge.tsx"

Cohesion: 0.17
Nodes (9): EntityWorkspaceLayout(), STATUS_CONFIGS, StatusBadge(), StatusConfig, WorkspaceTabDef, WorkspaceTabs(), Badge(), badgeVariants (+1 more)

### Community 10 - "app-sidebar.tsx"

Cohesion: 0.15
Nodes (20): AppSidebar(), SidebarContent(), SidebarFooter(), SidebarHeader(), canSee(), canSeePlatformPage(), iconMap, platformNavItems() (+12 more)

### Community 11 - "index.tsx"

Cohesion: 0.13
Nodes (18): CountryCode, countryCodes, parseE164(), sorted, PhoneInput(), Option, SearchableSelectProps, Command() (+10 more)

### Community 12 - "app-sidebar-layout.tsx"

Cohesion: 0.12
Nodes (14): AppContent(), Props, AppShell(), Props, NotificationSetupBanner(), SidebarInset(), SidebarProvider(), useNotificationBanner() (+6 more)

### Community 13 - "formatPrice"

Cohesion: 0.15
Nodes (17): InvoiceActionItem(), PaymentDetailSheet(), QueuePaymentSheet(), RecordPaymentForm(), SubmitPortalPaymentForm(), UnitDetailSheet(), UnitOverview(), PAYABLE_STATUSES (+9 more)

### Community 14 - "index.ts"

Cohesion: 0.13
Nodes (7): PropertyLayout(), UnitLayout(), PageProps, AvailableUnit, Lease, Property, Unit

### Community 15 - "formatDate"

Cohesion: 0.14
Nodes (11): LeaseEditSheet(), LeaseOptionGroup(), LeaseOptions(), TenantOverview(), formatDate(), LeaseSection(), LeaseWorkspace(), MaintenanceTickets() (+3 more)

### Community 16 - "index.tsx"

Cohesion: 0.17
Nodes (8): LeaseContextCard(), TenantLeaseContext(), AccountSummary(), PaymentRow(), PortalPayment, PortalPayment, Invoice, TenantLeaseContext

### Community 17 - "lease-detail-sheet.tsx"

Cohesion: 0.24
Nodes (11): LeaseDetailSheet(), LeaseOverview(), Collapsible(), CollapsibleContent(), CollapsibleTrigger(), BILLING_STRATEGIES, BILLING_UNITS, PAYMENT_METHOD_LABELS (+3 more)

### Community 18 - "utils.ts"

Cohesion: 0.20
Nodes (11): SegmentedToggle(), SegmentedToggleProps, Separator(), IsCurrentOrParentUrlFn, IsCurrentUrlFn, useCurrentUrl(), UseCurrentUrlReturn, WhenCurrentUrlFn (+3 more)

### Community 19 - "dashboard.tsx"

Cohesion: 0.15
Nodes (11): AccountSummary, AccountSummaryCard(), ActiveStayCard(), activityLabel(), activitySupport(), CurrentPaymentCard(), Lease, NextAction (+3 more)

### Community 20 - "index.tsx"

Cohesion: 0.24
Nodes (10): AppSidebarHeader(), Breadcrumbs(), Breadcrumb(), BreadcrumbEllipsis(), BreadcrumbItem(), BreadcrumbLink(), BreadcrumbList(), BreadcrumbPage() (+2 more)

### Community 21 - "Heading"

Cohesion: 0.20
Nodes (7): Heading(), PermissionEntry, PermissionGroup, RoleData, RoleFormData, RolePair, WorkspaceRole

### Community 22 - "index.ts"

Cohesion: 0.30
Nodes (4): columns, TenantLayout(), columns, WorkspaceTenant

### Community 23 - "rent.tsx"

Cohesion: 0.24
Nodes (10): InvoiceDetailSheet(), CollectionQueue(), PageProps, pendingReviewLabel(), Progress, REMINDER_LABELS, TabCounts, TABS (+2 more)

### Community 24 - "toggle-group.tsx"

Cohesion: 0.24
Nodes (9): SearchableSelect(), useComboboxAnchor(), SidebarMenuSkeleton(), ToggleGroup(), ToggleGroupContext, ToggleGroupItem(), Toggle(), toggleVariants (+1 more)

### Community 25 - "index.tsx"

Cohesion: 0.22
Nodes (8): formatDate(), Index(), ManagedUser, PageProps, Property, RoleOption, UserDetailSheet(), UserRole

### Community 27 - "settings.ts"

Cohesion: 0.29
Nodes (5): DynamicSettingsForm(), Driver, DynamicSettingsFormProps, MailConfig, SettingDefinition

### Community 28 - "index.tsx"

Cohesion: 0.48
Nodes (5): AlertError(), Alert(), AlertDescription(), AlertTitle(), alertVariants

### Community 29 - "useIsMobile"

Cohesion: 0.70
Nodes (4): getServerSnapshot(), isSmallerThanBreakpoint(), mediaQueryListener(), useIsMobile()

### Community 30 - "table.ts"

Cohesion: 0.40
Nodes (4): PaginationLinks, TableColumnMeta, TableFilterMeta, TableFilterOption

### Community 31 - "sort-header.tsx"

Cohesion: 0.67
Nodes (3): SortHeader(), SortHeaderProps, sortPart()

## Knowledge Gaps

- **135 isolated node(s):** `appName`, `FilterBarProps`, `DataTableProps`, `DataTablePaginationProps`, `SearchInputProps` (+130 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **4 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions

_Questions this graph is uniquely positioned to answer:_

- **Why does `cn()` connect `cn` to `button.tsx`, `tenant-documents-sheet.tsx`, `models.ts`, `index.tsx`, `use-appearance.tsx`, `app-header.tsx`, `sidebar.tsx`, `status-badge.tsx`, `app-sidebar.tsx`, `index.tsx`, `app-sidebar-layout.tsx`, `utils.ts`, `index.tsx`, `toggle-group.tsx`, `index.tsx`?**
  _High betweenness centrality (0.210) - this node is a cross-community bridge._
- **Why does `Button()` connect `button.tsx` to `tenant-documents-sheet.tsx`, `cn`, `models.ts`, `index.tsx`, `index.tsx`, `app-header.tsx`, `sidebar.tsx`, `status-badge.tsx`, `index.tsx`, `app-sidebar-layout.tsx`, `formatPrice`, `index.tsx`, `lease-detail-sheet.tsx`, `utils.ts`, `dashboard.tsx`, `rent.tsx`, `index.tsx`, `formatters.ts`?**
  _High betweenness centrality (0.095) - this node is a cross-community bridge._
- **Why does `formatDate()` connect `formatDate` to `button.tsx`, `tenant-documents-sheet.tsx`, `index.tsx`, `index.tsx`, `formatPrice`, `index.ts`, `index.tsx`, `lease-detail-sheet.tsx`, `dashboard.tsx`, `index.ts`, `rent.tsx`, `formatters.ts`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._
- **What connects `appName`, `FilterBarProps`, `DataTableProps` to the rest of the system?**
  _135 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `button.tsx` be split into smaller, more focused modules?**
  _Cohesion score 0.0705870086539464 - nodes in this community are weakly interconnected._
- **Should `tenant-documents-sheet.tsx` be split into smaller, more focused modules?**
  _Cohesion score 0.05738615327656423 - nodes in this community are weakly interconnected._
- **Should `cn` be split into smaller, more focused modules?**
  _Cohesion score 0.05902980713033314 - nodes in this community are weakly interconnected._
