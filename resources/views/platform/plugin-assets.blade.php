@php
    $pluginAssetScope = in_array($scope ?? null, ['admin', 'frontend', 'guest'], true) ? $scope : 'frontend';
    $pluginAssetKind = ($kind ?? null) === 'scripts' ? 'scripts' : 'styles';

    try {
        $pluginAssetEntries = app(\App\Platform\Core\Services\PluginAssetRegistry::class)
            ->assets($pluginAssetScope)[$pluginAssetKind] ?? [];
    } catch (\Throwable $exception) {
        report($exception);
        $pluginAssetEntries = [];
    }
@endphp

@foreach ($pluginAssetEntries as $pluginAsset)
    @if ($pluginAssetKind === 'styles')
        <link
            rel="stylesheet"
            href="{{ $pluginAsset['url'] }}"
            data-plugin-asset="{{ $pluginAsset['slug'] }}"
            data-plugin-asset-scope="{{ $pluginAssetScope }}"
            data-plugin-asset-path="{{ $pluginAsset['path'] }}"
        >
    @else
        <script
            src="{{ $pluginAsset['url'] }}"
            data-plugin-asset="{{ $pluginAsset['slug'] }}"
            data-plugin-asset-scope="{{ $pluginAssetScope }}"
            data-plugin-asset-path="{{ $pluginAsset['path'] }}"
            defer
        ></script>
    @endif
@endforeach
