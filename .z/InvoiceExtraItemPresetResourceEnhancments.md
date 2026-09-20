How we could implement it (The Options)
1. The "Redirect with Filter" Approach (Best of both worlds) Instead of rebuilding the preset management, we keep the single InvoiceExtraItemPresetResource. On the ListSaleInvoices page, we add a header action:

php
Action::make('manage_presets')
    ->icon('heroicon-o-cog')
    ->label('Manage Presets')
    ->url(InvoiceExtraItemPresetResource::getUrl('index', ['type' => 'sale_invoice']))
When clicked, it redirects to the Preset resource. The Preset resource reads the ?type=sale_invoice URL parameter and automatically:

Filters the table to only show Sale Invoice presets.
Hydrates a hidden invoice_type field in the creation form, so any new preset created from this view automatically belongs to sale_invoice.
2. The Filament "Cluster" Approach Filament v3 introduced Clusters. You could create a SalesCluster. Inside it, you register the SaleInvoiceResource and a brand new SaleInvoicePresetResource. This completely separates it in the sidebar. The downside: You would have to duplicate the Preset Resource 4 times (one for each invoice type), which completely violates the DRY (Don't Repeat Yourself) principle.

3. The Modal Approach (Not Recommended) You could technically add an action to the ListSaleInvoices page that opens a modal containing a Livewire component that renders a Filament Table of presets. The downside: Putting a full CRUD (Create, Read, Update, Delete) table inside a popup modal usually results in a cramped, confusing User Experience.

My Architectural Recommendation
Honestly, keeping it as a single, separate resource is actually the better architectural pattern. Here is why:

Separation of Concerns (Operations vs. Configuration): The SaleInvoiceResource is an operational page. Cashiers and accountants go there 50 times a day to ring up sales and process returns. Presets are configuration data. A manager or admin sets them up once (or rarely) and forgets about them. Mixing system configuration into daily operational pages clutters the UI for the everyday user.

Centralized Settings: In massive ERP systems, administrators prefer having a dedicated "Settings" or "Configurations" area in the sidebar where they can manage all Presets, Taxes, Roles, and Categories in one unified place, rather than hunting through different operational pages to find specific settings.

Code Cleanliness: Your current setup is perfectly DRY. You have one table, one form, and we just added those beautiful Enum badges to group them visually.

The Verdict: If you want to make it easier for managers to jump to the presets from the invoice page, I would highly recommend Option 1 (Redirect with Filter) for the future. It gives you the quick-access link directly from the Sale Invoices page, but keeps the underlying code beautifully centralized!