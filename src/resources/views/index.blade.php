@extends('web::layouts.grids.12')

@section('title', trans('contracts::global.title'))
@section('page_header', trans('contracts::global.title'))
@section('page_description', trans('contracts::global.description'))

@php($isk = fn ($v) => $v === null ? '–' : number_format($v, 0, '.', "'") . ' ISK')
@php($n = fn ($v) => number_format($v, 0, '.', "'"))

@section('full')
  @if (! $status['last_seen'])
    <div class="alert alert-info">{{ trans('contracts::global.no_data') }}</div>
  @endif
  @if (! $status['jita'])
    <div class="alert alert-warning">{{ trans('contracts::global.no_jita') }}</div>
  @endif

  <div class="card">
    <div class="card-header"><h3 class="card-title">{{ trans('contracts::global.collection') }}</h3></div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <tbody>
          <tr><th>{{ trans('contracts::global.last_seen') }}</th><td>{{ $status['last_seen'] ? $status['last_seen'] . ' UTC' : '–' }}</td></tr>
          <tr><th>{{ trans('contracts::global.open') }}</th><td>{{ $n($status['open']) }}</td></tr>
          <tr><th class="pl-4 font-weight-normal">{{ trans('contracts::global.read') }}</th><td>{{ $n($status['read']) }}</td></tr>
          <tr><th class="pl-4 font-weight-normal">{{ trans('contracts::global.pending') }}</th><td>{{ $n($status['pending']) }}</td></tr>
          <tr><th class="pl-4 font-weight-normal">{{ trans('contracts::global.unavailable') }}</th><td>{{ $n($status['unavailable']) }}</td></tr>
          <tr><th>{{ trans('contracts::global.gone') }}</th><td>{{ $n($status['gone']) }}</td></tr>
          <tr><th>{{ trans('contracts::global.items') }}</th><td>{{ $n($status['items']) }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title">{{ trans('contracts::global.capitals') }}</h3></div>
    <div class="card-body p-0">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>{{ trans('contracts::global.col_group') }}</th>
            <th class="text-right">{{ trans('contracts::global.col_open') }}</th>
            <th class="text-right">{{ trans('contracts::global.col_priced') }}</th>
            <th class="text-right">{{ trans('contracts::global.col_lowest') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($status['groups'] as $group)
            <tr>
              <td>
                {{ $group['name'] }}
                @unless ($group['found'])
                  <span class="badge badge-warning" title="{{ trans('contracts::global.group_missing_help') }}">{{ trans('contracts::global.group_missing') }}</span>
                @endunless
              </td>
              <td class="text-right">{{ $n($group['open']) }}</td>
              <td class="text-right">{{ $n($group['priced']) }}</td>
              <td class="text-right">{{ $isk($group['lowest']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer small text-muted">{{ trans('contracts::global.capitals_note') }}</div>
  </div>
@endsection
