@extends('layout.app')

@section('meta-information')
    <title>Monthly Report</title>
@endsection

@section('css')
    @include('report.partials.fr-styles')
@endsection

@section('main-content')
@include('report.partials.account-report-tabs')

<div class="fr-shell">
    @include('report.partials.fr-header')

    <div class="fr-card" style="margin-top:14px">
        <div class="fr-body">
            @include('report.statements._monthly')
        </div>
    </div>
</div>
@endsection