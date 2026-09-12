<?php

namespace Modules\Invoices\Filament\Admin\Resources\PeppolIntegrations;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Modules\Invoices\Filament\Admin\Resources\PeppolIntegrations\Pages\CreatePeppolIntegration;
use Modules\Invoices\Filament\Admin\Resources\PeppolIntegrations\Pages\EditPeppolIntegration;
use Modules\Invoices\Filament\Admin\Resources\PeppolIntegrations\Pages\ListPeppolIntegrations;
use Modules\Invoices\Filament\Admin\Resources\PeppolIntegrations\Schemas\PeppolIntegrationForm;
use Modules\Invoices\Filament\Admin\Resources\PeppolIntegrations\Tables\PeppolIntegrationsTable;
use Modules\Invoices\Models\PeppolIntegration;
use UnitEnum;

class PeppolIntegrationResource extends Resource
{
    protected static ?string $model = PeppolIntegration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?string $navigationLabel = 'Peppol Integrations';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    public static function getPages(): array
    {
        return [
            'index'  => ListPeppolIntegrations::route('/'),
            'create' => CreatePeppolIntegration::route('/create'),
            'edit'   => EditPeppolIntegration::route('/{record}/edit'),
        ];
    }

    public static function getFormSchema(): array
    {
        return PeppolIntegrationForm::configure(
            app(\Filament\Schemas\Schema::class)
        )->getComponents();
    }

    public static function getTableSchema(): array
    {
        return PeppolIntegrationsTable::configure(
            app(\Filament\Tables\Table::class)
        )->getColumns();
    }
}
