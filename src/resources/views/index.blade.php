@extends('web::layouts.grids.12')

@section('title', trans('contracts::global.title'))
@section('page_header', trans('contracts::global.title'))
@section('page_description', trans('contracts::global.description'))

@php
  // Short ISK for the tables (2.18 B), the full amount in the tooltip.
  $short = function ($v) {
      if ($v === null) return '–';
      $a = abs($v);
      if ($a >= 1e9) return number_format($v / 1e9, 2, '.', "'") . ' B';
      if ($a >= 1e6) return number_format($v / 1e6, 2, '.', "'") . ' M';
      if ($a >= 1e3) return number_format($v / 1e3, 1, '.', "'") . ' k';
      return number_format($v, 0, '.', "'");
  };
  $full = fn ($v) => $v === null ? '' : number_format($v, 2, '.', "'") . ' ISK';
  $n = fn ($v) => number_format($v, 0, '.', "'");
  // Security status as the game shows it (one decimal), coloured high / low / null.
  $sec = fn ($s) => $s === null ? null : ['text' => number_format(round($s, 1), 1), 'class' => $s >= 0.45 ? 'ct-high' : ($s > 0 ? 'ct-low' : 'ct-null')];
@endphp

@section('full')
  @if (! $status['last_seen'])
    <div class="alert alert-info">{{ trans('contracts::global.no_data') }}</div>
  @endif
  @if (! $status['jita'])
    <div class="alert alert-warning">{{ trans('contracts::global.no_jita') }}</div>
  @endif

  <div class="card">
    <div class="card-body py-2 d-flex flex-wrap align-items-center">
      <label for="ct-character" class="ct-label mr-2 mb-0">{{ trans('contracts::global.open_with') }}</label>
      <select id="ct-character" class="form-control form-control-sm mr-3" style="width: auto">
        @foreach ($characters as $c)
          <option value="{{ $c['id'] }}" @selected($c['id'] === $selected) @disabled(! $c['scope'])>
            {{ $c['name'] }}{{ $c['online'] === true ? ' ●' : '' }}{{ $c['scope'] ? '' : ' – ' . trans('contracts::global.no_scope') }}
          </option>
        @endforeach
      </select>
      <small class="text-muted">{{ trans('contracts::global.open_help') }}</small>
      <button id="ct-expand" type="button" class="btn btn-sm btn-outline-secondary ml-auto">{{ trans('contracts::global.expand_all') }}</button>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0 table-responsive">
      <table class="table table-sm mb-0 ct-table">
        <thead>
          <tr>
            <th>{{ trans('contracts::global.col_ship') }}</th>
            <th class="text-right">{{ trans('contracts::global.col_open') }}</th>
            <th class="text-right" title="{{ trans('contracts::global.counted_help') }}">{{ trans('contracts::global.col_counted') }}</th>
            <th class="text-right">{{ trans('contracts::global.col_lowest') }}</th>
            <th class="text-right">{{ trans('contracts::global.col_median') }}</th>
            <th class="text-right">{{ trans('contracts::global.col_average') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($tree as $g => $group)
            <tr class="ct-group" data-group="{{ $g }}" @if ($group['open']) role="button" tabindex="0" aria-expanded="false" @endif>
              <td>
                @if ($group['open'])<i class="fas fa-caret-right ct-caret"></i>@else<i class="fas fa-caret-right ct-caret invisible"></i>@endif
                {{ $group['name'] }}
                @unless ($group['group_id'])
                  <span class="badge badge-warning" title="{{ trans('contracts::global.group_missing_help') }}">{{ trans('contracts::global.group_missing') }}</span>
                @endunless
              </td>
              <td class="text-right">{{ $n($group['open']) }}</td>
              <td class="text-right">{{ $n($group['figures']['count']) }}</td>
              <td class="text-right" title="{{ $full($group['figures']['lowest']) }}">{{ $short($group['figures']['lowest']) }}</td>
              <td class="text-right font-weight-bold" title="{{ $full($group['figures']['median']) }}">{{ $short($group['figures']['median']) }}</td>
              <td class="text-right" title="{{ $full($group['figures']['average']) }}">{{ $short($group['figures']['average']) }}</td>
            </tr>
            @foreach ($group['hulls'] as $hull)
              <tr class="ct-hull d-none" data-of-group="{{ $g }}" data-hull="{{ $hull['type_id'] }}" role="button" tabindex="0" aria-expanded="false">
                <td class="pl-4">
                  <i class="fas fa-caret-right ct-caret"></i>
                  {!! img('types', 'icon', $hull['type_id'], 32, ['class' => 'img-circle eve-icon small-icon ct-icon'], false) !!}
                  {{ $hull['name'] }}
                </td>
                <td class="text-right">{{ $n($hull['open']) }}</td>
                <td class="text-right">{{ $n($hull['figures']['count']) }}</td>
                <td class="text-right" title="{{ $full($hull['figures']['lowest']) }}">{{ $short($hull['figures']['lowest']) }}</td>
                <td class="text-right font-weight-bold" title="{{ $full($hull['figures']['median']) }}">{{ $short($hull['figures']['median']) }}</td>
                <td class="text-right" title="{{ $full($hull['figures']['average']) }}">{{ $short($hull['figures']['average']) }}</td>
              </tr>
              <tr class="ct-contracts d-none" data-of-group="{{ $g }}" data-of-hull="{{ $hull['type_id'] }}">
                <td colspan="6" class="p-0">
                  <table class="table table-sm mb-0 ct-sub">
                    <thead>
                      <tr>
                        <th class="text-right">{{ trans('contracts::global.col_ship_price') }}</th>
                        <th class="text-right">{{ trans('contracts::global.col_price') }}</th>
                        <th class="text-right" title="{{ trans('contracts::global.fitting_help') }}">{{ trans('contracts::global.col_fitting') }}</th>
                        <th>{{ trans('contracts::global.col_location') }}</th>
                        <th>{{ trans('contracts::global.col_issuer') }}</th>
                        <th>{{ trans('contracts::global.col_expires') }}</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($hull['contracts'] as $c)
                        @php($s = $sec($c['security']))
                        <tr class="{{ $c['counted'] ? '' : 'ct-uncounted' }}">
                          <td class="text-right font-weight-bold" title="{{ $full($c['net_price']) }}">
                            {{ $short($c['net_price']) }}
                            @if ($c['outlier'])
                              <span class="badge badge-secondary" title="{{ trans('contracts::global.outlier_help') }}">{{ trans('contracts::global.outlier') }}</span>
                            @endif
                            @if ($c['valuation'] === 'multiple')
                              <span class="badge badge-info" title="{{ trans('contracts::global.multiple_help') }}">{{ trans('contracts::global.multiple') }}</span>
                            @elseif ($c['valuation'] === 'requests')
                              <span class="badge badge-info" title="{{ trans('contracts::global.requests_help') }}">{{ trans('contracts::global.requests') }}</span>
                            @endif
                          </td>
                          <td class="text-right" title="{{ $full($c['type'] === 'auction' ? $c['buyout'] : $c['price']) }}">
                            @if ($c['type'] === 'auction')
                              <span class="badge badge-light" title="{{ trans('contracts::global.auction_help', ['bid' => $short($c['price'])]) }}">{{ trans('contracts::global.auction') }}</span>
                              {{ $short($c['buyout']) }}
                            @else
                              {{ $short($c['price']) }}
                            @endif
                          </td>
                          <td class="text-right" title="{{ $full($c['extras_value']) }}">
                            {{ $c['extras_value'] ? $short($c['extras_value']) : '–' }}
                            @if ($c['extras_unpriced'])
                              <span class="badge badge-warning" title="{{ trans('contracts::global.unpriced_help') }}">{{ trans('contracts::global.unpriced', ['count' => $c['extras_unpriced']]) }}</span>
                            @endif
                          </td>
                          <td class="ct-wrap">
                            @if ($s)<span class="{{ $s['class'] }}">{{ $s['text'] }}</span>@endif
                            {{ $c['location'] ?? ($c['structure'] ? trans('contracts::global.unknown_structure') : trans('contracts::global.unknown_location')) }}
                            <div class="ct-sub-text">{{ collect([$c['system'], $c['region']])->filter()->implode(' · ') }}</div>
                          </td>
                          <td class="ct-wrap">
                            {{ $c['issuer'] ?? '–' }}
                            @if ($c['title'])<div class="ct-sub-text">{{ $c['title'] }}</div>@endif
                          </td>
                          <td title="{{ trans('contracts::global.issued', ['date' => $c['issued']]) }}">
                            {{ $c['expires'] }}
                            <div class="ct-sub-text">{{ trans_choice('contracts::global.days_left', $c['days_left'], ['days' => $c['days_left']]) }}</div>
                          </td>
                          <td class="text-right text-nowrap">
                            <button type="button" class="btn btn-xs btn-outline-secondary ct-items" data-contract="{{ $c['contract_id'] }}">{{ trans('contracts::global.contents') }}</button>
                            <button type="button" class="btn btn-xs btn-primary ct-open" data-contract="{{ $c['contract_id'] }}" title="{{ trans('contracts::global.open_in_game_help') }}">
                              <i class="fas fa-external-link-alt"></i> {{ trans('contracts::global.open_in_game') }}
                            </button>
                          </td>
                        </tr>
                        <tr class="ct-items-row d-none" data-items-of="{{ $c['contract_id'] }}"><td colspan="7" class="ct-items-cell"></td></tr>
                      @endforeach
                    </tbody>
                  </table>
                </td>
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer small text-muted">{{ trans('contracts::global.figures_note') }}</div>
  </div>

  <div class="card collapsed-card">
    <div class="card-header">
      <h3 class="card-title">{{ trans('contracts::global.collection') }}</h3>
      <div class="card-tools">
        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
      </div>
    </div>
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

  <div id="ct-toast" class="ct-toast d-none" role="status" aria-live="polite"></div>
@endsection

@push('head')
  <style>
    .ct-label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; }
    .ct-table { font-size: .875rem; }
    .ct-table td { vertical-align: middle; white-space: nowrap; }
    .ct-table td.ct-wrap { white-space: normal; min-width: 10rem; }
    .ct-group { font-weight: 600; cursor: pointer; }
    .ct-hull { cursor: pointer; }
    .ct-caret { width: .8rem; transition: transform .15s; }
    [aria-expanded="true"] > td > .ct-caret { transform: rotate(90deg); }
    .ct-icon { width: 24px; height: 24px; margin: 0 .35rem; }
    .ct-sub { font-size: .8125rem; background: rgba(0, 0, 0, .02); }
    .ct-sub th { font-weight: 600; color: #6c757d; border-top: 0; }
    .ct-sub > tbody > tr > td:first-child, .ct-sub > thead > tr > th:first-child { padding-left: 3rem; }
    .ct-sub-text { font-size: .75rem; color: #6c757d; }
    tr.ct-uncounted > td { opacity: .6; }
    .ct-high { color: #28a745; font-weight: 600; }
    .ct-low { color: #fd7e14; font-weight: 600; }
    .ct-null { color: #dc3545; font-weight: 600; }
    .ct-items-cell { padding: .25rem 1rem .5rem 3rem !important; }
    .ct-items-list { columns: 2 22rem; column-gap: 2rem; margin: 0; padding: 0; list-style: none; }
    .ct-items-list li { display: flex; justify-content: space-between; gap: 1rem; break-inside: avoid; }
    .ct-items-list .ct-wanted { color: #dc3545; }
    .ct-toast { position: fixed; right: 1rem; bottom: 1rem; z-index: 1080; max-width: 28rem; }
    .btn-xs { padding: .1rem .4rem; font-size: .75rem; }
  </style>
@endpush

@push('javascript')
  <script>
    $(function () {
      var urls = {
        items: @json(route('seatcontracts::items', ['contractId' => 0])).replace(/0$/, ''),
        open: @json(route('seatcontracts::open-window'))
      };
      var t = @json(trans('contracts::global'));
      var csrf = $('meta[name="csrf-token"]').attr('content');
      var toastTimer;

      function tr(key, repl) {
        var s = t[key] || key;
        Object.keys(repl || {}).forEach(function (k) { s = s.split(':' + k).join(repl[k]); });
        return s;
      }
      function toast(message, kind) {
        var box = $('#ct-toast');
        box.attr('class', 'ct-toast alert alert-' + (kind || 'success')).text(message);
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { box.addClass('d-none'); }, 6000);
      }
      function isk(v) {
        if (v === null) return '–';
        var a = Math.abs(v);
        if (a >= 1e9) return (v / 1e9).toFixed(2) + ' B';
        if (a >= 1e6) return (v / 1e6).toFixed(2) + ' M';
        if (a >= 1e3) return (v / 1e3).toFixed(1) + ' k';
        return Math.round(v).toString();
      }

      function setGroup(row, open) {
        row.attr('aria-expanded', open ? 'true' : 'false');
        var g = row.data('group');
        $('.ct-hull[data-of-group="' + g + '"]').toggleClass('d-none', !open);
        if (!open) {
          $('.ct-hull[data-of-group="' + g + '"]').attr('aria-expanded', 'false');
          $('.ct-contracts[data-of-group="' + g + '"]').addClass('d-none');
        }
      }
      function setHull(row, open) {
        row.attr('aria-expanded', open ? 'true' : 'false');
        $('.ct-contracts[data-of-hull="' + row.data('hull') + '"][data-of-group="' + row.data('of-group') + '"]').toggleClass('d-none', !open);
      }
      function onActivate(selector, fn) {
        $(document).on('click', selector, function () { fn($(this)); });
        $(document).on('keydown', selector, function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fn($(this)); }
        });
      }
      onActivate('.ct-group[role="button"]', function (row) { setGroup(row, row.attr('aria-expanded') !== 'true'); });
      onActivate('.ct-hull', function (row) { setHull(row, row.attr('aria-expanded') !== 'true'); });

      var expanded = false;
      $('#ct-expand').on('click', function () {
        expanded = !expanded;
        $('.ct-group[role="button"]').each(function () { setGroup($(this), expanded); });
        if (expanded) $('.ct-hull').each(function () { setHull($(this), true); });
        $(this).text(tr(expanded ? 'collapse_all' : 'expand_all'));
      });

      $(document).on('click', '.ct-items', function () {
        var id = $(this).data('contract');
        var row = $('tr[data-items-of="' + id + '"]');
        if (!row.hasClass('d-none')) { row.addClass('d-none'); return; }
        row.removeClass('d-none');
        var cell = row.children('td');
        if (cell.data('loaded')) return;
        cell.text(tr('loading'));
        $.getJSON(urls.items + id).done(function (data) {
          var list = $('<ul class="ct-items-list"></ul>');
          data.items.forEach(function (i) {
            var name = i.quantity + ' × ' + i.name;
            if (i.bpc) name += ' (' + tr('bpc', { runs: i.runs, me: i.me, te: i.te }) + ')';
            var li = $('<li></li>').toggleClass('ct-wanted', !i.included);
            li.append($('<span></span>').text(name + (i.included ? '' : ' – ' + tr('wanted'))));
            li.append($('<span class="text-muted"></span>').text(i.capital ? '' : isk(i.value)));
            list.append(li);
          });
          cell.empty().append(data.items.length ? list : $('<span></span>').text(tr('no_items')));
          cell.data('loaded', true);
        }).fail(function (xhr) {
          cell.text((xhr.responseJSON && xhr.responseJSON.message) || tr('error'));
        });
      });

      $(document).on('click', '.ct-open', function () {
        var character = $('#ct-character').val();
        if (!character) { toast(tr('no_character'), 'warning'); return; }
        var btn = $(this).prop('disabled', true);
        $.ajax({
          url: urls.open, method: 'POST', dataType: 'json',
          headers: { 'X-CSRF-TOKEN': csrf },
          data: { character_id: character, contract_id: btn.data('contract') }
        }).done(function (data) {
          toast(data.message, data.offline ? 'warning' : 'success');
        }).fail(function (xhr) {
          toast((xhr.responseJSON && xhr.responseJSON.message) || tr('error'), 'danger');
        }).always(function () { btn.prop('disabled', false); });
      });
    });
  </script>
@endpush
