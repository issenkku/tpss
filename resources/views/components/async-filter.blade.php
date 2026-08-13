@props(['scope'])

<div data-async-filter-scope="{{ $scope }}" {{ $attributes }}>
    {{ $slot }}
</div>
