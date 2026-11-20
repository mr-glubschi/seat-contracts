@extends('web::layouts.grids.12')

@section('title', trans('contracts::global.title'))
@section('page_header', trans('contracts::global.title'))
@section('page_description', trans('contracts::global.description'))

@php
  // Short ISK for the tables (2.18 B), the full amount in the tooltip. The page script has the same rules.
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
  $query = array_filter($filters, fn ($v) => $v !== null && $v !== false);
@endphp

@section('full')
  @if (! $status['last_seen'])
    <div class="alert alert-info">{{ trans('contracts::global.no_data') }}</div>
  @endif
  @if (! $status['jita'])
    <div class="alert alert-warning">{{ trans('contracts::global.no_jita') }}</div>
  @endif

  <div class="card">
    <div class="card-body py-2">
      <form id="ct-filters" method="get" action="{{ route('seatcontracts::home') }}" class="d-flex flex-wrap align-items-center">
        <label for="ct-security" class="ct-label mr-2 mb-2">{{ trans('contracts::global.security') }}</label>
        <select id="ct-security" name="security" class="form-control form-control-sm mr-3 mb-2" style="width: auto">
          <option value="">{{ trans('contracts::global.all') }}</option>
          @foreach (['high', 'low', 'null'] as $band)
            <option value="{{ $band }}" @selected($filters['security'] === $band)>{{ trans('contracts::global.sec_' . $band) }}</option>
          @endforeach
        </select>
        <label for="ct-region" class="ct-label mr-2 mb-2">{{ trans('contracts::global.region') }}</label>
        <select id="ct-region" name="region" class="form-control form-control-sm mr-3 mb-2" style="width: auto; max-width: 14rem">
          <option value="">{{ trans('contracts::global.all') }}</option>
          @foreach ($regions as $id => $name)
            <option value="{{ $id }}" @selected($filters['region'] === $id)>{{ $name }}</option>
          @endforeach
        </select>
        <div class="custom-control custom-checkbox mr-3 mb-2">
          <input type="checkbox" class="custom-control-input" id="ct-located" name="located" value="1" @checked($filters['located'])>
          <label class="custom-control-label" for="ct-located">{{ trans('contracts::global.only_located') }}</label>
        </div>
        @if ($query)
          <a href="{{ route('seatcontracts::home') }}" class="btn btn-sm btn-link mb-2">{{ trans('contracts::global.reset') }}</a>
        @endif
        <noscript><button type="submit" class="btn btn-sm btn-primary mb-2">{{ trans('contracts::global.apply') }}</button></noscript>

        <span class="ml-auto"></span>
        <label for="ct-character" class="ct-label mr-2 mb-2">{{ trans('contracts::global.open_with') }}</label>
        <select id="ct-character" class="form-control form-control-sm mb-2" style="width: auto" title="{{ trans('contracts::global.open_help') }}">
          @foreach ($characters as $c)
            <option value="{{ $c['id'] }}" @selected($c['id'] === $selected) @disabled(! $c['scope'])>
              {{ $c['name'] }}{{ $c['online'] === true ? ' ●' : '' }}{{ $c['scope'] ? '' : ' – ' . trans('contracts::global.no_scope') }}
            </option>
          @endforeach
        </select>
      </form>
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
            <th class="text-right" title="{{ trans('contracts::global.trend_help') }}">{{ trans('contracts::global.col_trend7') }}</th>
            <th class="text-right" title="{{ trans('contracts::global.trend_help') }}">{{ trans('contracts::global.col_trend30') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($tree as $g => $group)
            <tr class="ct-group" data-key="g{{ $g }}" @if ($group['open']) role="button" tabindex="0" aria-expanded="false" @endif>
              <td>
                <i class="fas fa-caret-right ct-caret {{ $group['open'] ? '' : 'invisible' }}"></i>
                {{ $group['name'] }}
                @unless ($group['group_id'])
                  <span class="badge badge-warning" title="{{ trans('contracts::global.group_missing_help') }}">{{ trans('contracts::global.group_missing') }}</span>
                @endunless
              </td>
              <td class="text-right">{{ $n($group['open']) }}</td>
              <td></td>
              <td class="text-right" title="{{ $full($group['lowest']) }}">{{ $short($group['lowest']) }}</td>
              <td colspan="4"></td>
            </tr>
            @foreach ($group['segments'] as $s => $segment)
              <tr class="ct-segment d-none" data-parent="g{{ $g }}" data-key="g{{ $g }}s{{ $s }}" role="button" tabindex="0" aria-expanded="false">
                <td class="pl-4">
                  <i class="fas fa-caret-right ct-caret"></i>
                  {{ trans('contracts::global.segment_' . $segment['segment']) }}
                </td>
                <td class="text-right">{{ $n($segment['open']) }}</td>
                <td class="text-right">{{ $n($segment['figures']['count']) }}</td>
                <td class="text-right" title="{{ $full($segment['figures']['lowest']) }}">{{ $short($segment['figures']['lowest']) }}</td>
                <td class="text-right font-weight-bold" title="{{ $full($segment['figures']['median']) }}">{{ $short($segment['figures']['median']) }}</td>
                <td class="text-right" title="{{ $full($segment['figures']['average']) }}">{{ $short($segment['figures']['average']) }}</td>
                <td colspan="2"></td>
              </tr>
              @foreach ($segment['hulls'] as $hull)
                <tr class="ct-hull d-none" data-parent="g{{ $g }}s{{ $s }}" data-key="h{{ $hull['type_id'] }}" data-hull="{{ $hull['type_id'] }}" role="button" tabindex="0" aria-expanded="false">
                  <td class="ct-indent-2">
                    <i class="fas fa-caret-right ct-caret"></i>
                    {!! img('types', 'icon', $hull['type_id'], 32, ['class' => 'img-circle eve-icon small-icon ct-icon'], false) !!}
                    {{ $hull['name'] }}
                  </td>
                  <td class="text-right">{{ $n($hull['open']) }}</td>
                  <td class="text-right">{{ $n($hull['figures']['count']) }}</td>
                  <td class="text-right" title="{{ $full($hull['figures']['lowest']) }}">{{ $short($hull['figures']['lowest']) }}</td>
                  <td class="text-right font-weight-bold" title="{{ $full($hull['figures']['median']) }}">{{ $short($hull['figures']['median']) }}</td>
                  <td class="text-right" title="{{ $full($hull['figures']['average']) }}">{{ $short($hull['figures']['average']) }}</td>
                  @foreach (['trend7', 'trend30'] as $t)
                    @php($tr = $hull[$t])
                    <td class="text-right {{ $tr === null ? '' : ($tr['pct'] > 0.5 ? 'ct-up' : ($tr['pct'] < -0.5 ? 'ct-down' : '')) }}"
                        title="{{ $tr !== null && $tr['estimated'] ? trans('contracts::global.estimated_help') : '' }}">
                      @if ($tr === null)
                        –
                      @else
                        {{ $tr['estimated'] ? '≈ ' : '' }}{{ ($tr['pct'] > 0 ? '+' : '') . number_format($tr['pct'], 1) }} %
                      @endif
                    </td>
                  @endforeach
                </tr>
                <tr class="ct-detail d-none" data-parent="h{{ $hull['type_id'] }}" data-detail-of="{{ $hull['type_id'] }}">
                  <td colspan="8" class="p-0 ct-detail-cell"></td>
                </tr>
              @endforeach
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer small text-muted">{{ trans('contracts::global.figures_note', ['days' => config('contracts.history_days')]) }}</div>
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
          <tr><th>{{ trans('contracts::global.keep_items') }}</th><td>{{ config('contracts.keep_items_days') ? trans('contracts::global.keep_items_days', ['days' => config('contracts.keep_items_days')]) : trans('contracts::global.keep_items_forever') }}</td></tr>
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
    .ct-group { font-weight: 600; cursor: pointer; }
    .ct-segment, .ct-hull { cursor: pointer; }
    .ct-segment { font-weight: 500; }
    .ct-indent-2 { padding-left: 2.75rem !important; }
    .ct-caret { width: .8rem; transition: transform .15s; }
    [aria-expanded="true"] > td > .ct-caret { transform: rotate(90deg); }
    .ct-icon { width: 24px; height: 24px; margin: 0 .35rem; }
    .ct-up { color: #dc3545; }
    .ct-down { color: #28a745; }
    .ct-detail-cell { background: rgba(0, 0, 0, .02); }
    .ct-chart-box { position: relative; height: 220px; margin: .75rem 1rem .25rem 3.5rem; }
    .ct-chart-note { margin: 0 1rem .5rem 3.5rem; font-size: .75rem; color: #6c757d; }
    .ct-sub { font-size: .8125rem; }
    .ct-sub th { font-weight: 600; color: #6c757d; border-top: 0; white-space: nowrap; }
    .ct-sub td.ct-wrap { white-space: normal; min-width: 10rem; }
    .ct-sub > tbody > tr > td:first-child, .ct-sub > thead > tr > th:first-child { padding-left: 3.5rem; }
    .ct-sort { white-space: nowrap; text-decoration: none !important; color: inherit; }
    .ct-sub-text { font-size: .75rem; color: #6c757d; }
    tr.ct-uncounted > td { opacity: .6; }
    .ct-high { color: #28a745; font-weight: 600; }
    .ct-low { color: #fd7e14; font-weight: 600; }
    .ct-null { color: #dc3545; font-weight: 600; }
    .ct-items-cell { padding: .25rem 1rem .5rem 3.5rem !important; }
    .ct-items-list { columns: 2 22rem; column-gap: 2rem; margin: 0; padding: 0; list-style: none; }
    .ct-items-list li { display: flex; justify-content: space-between; gap: 1rem; break-inside: avoid; }
    .ct-items-list .ct-wanted { color: #dc3545; }
    .ct-items-list .ct-rig { color: #6c757d; }
    .ct-toast { position: fixed; right: 1rem; bottom: 1rem; z-index: 1080; max-width: 28rem; }
    .btn-xs { padding: .1rem .4rem; font-size: .75rem; }
  </style>
@endpush

@push('javascript')
  <script>
    $(function () {
      var urls = {
        hull: @json(route('seatcontracts::hull', ['typeId' => 0])).replace(/0$/, ''),
        items: @json(route('seatcontracts::items', ['contractId' => 0])).replace(/0$/, ''),
        open: @json(route('seatcontracts::open-window'))
      };
      var query = @json($query);
      var t = @json(trans('contracts::global'));
      var csrf = $('meta[name="csrf-token"]').attr('content');
      var SORT_KEY = 'glubschi.contracts.sort';
      var sort = { key: 'net_price', dir: 1 };
      try {
        var saved = JSON.parse(localStorage.getItem(SORT_KEY) || 'null');
        if (saved && saved.key && (saved.dir === 1 || saved.dir === -1)) sort = saved;
      } catch (e) { /* no storage: default sort */ }
      var hulls = {};   // type ID => loaded data
      var toastTimer;

      function tr(key, repl) {
        var s = t[key] || key;
        Object.keys(repl || {}).forEach(function (k) { s = s.split(':' + k).join(repl[k]); });
        return s;
      }
      function el(tag, cls, text) {
        var e = $('<' + tag + '></' + tag + '>');
        if (cls) e.addClass(cls);
        if (text !== undefined && text !== null) e.text(text);
        return e;
      }
      function isk(v) {
        if (v === null || v === undefined) return '–';
        var a = Math.abs(v), s;
        if (a >= 1e9) s = (v / 1e9).toFixed(2) + ' B';
        else if (a >= 1e6) s = (v / 1e6).toFixed(2) + ' M';
        else if (a >= 1e3) s = (v / 1e3).toFixed(1) + ' k';
        else s = Math.round(v).toString();
        return s.replace(/\B(?=(\d{3})+(?!\d))/g, "'");
      }
      function full(v) {
        return v === null || v === undefined ? '' : v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, "'") + ' ISK';
      }
      function badge(kind, text, title) { return el('span', 'badge badge-' + kind + ' ml-1', text).attr('title', title || ''); }
      function toast(message, kind) {
        var box = $('#ct-toast');
        box.attr('class', 'ct-toast alert alert-' + (kind || 'success')).text(message);
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { box.addClass('d-none'); }, 6000);
      }

      // Filters apply when changed.
      $('#ct-security, #ct-region, #ct-located').on('change', function () { $('#ct-filters').trigger('submit'); });
      $('#ct-filters').on('submit', function () {
        $(this).find('select, input').each(function () { if (!$(this).val() || (this.type === 'checkbox' && !this.checked)) $(this).prop('disabled', true); });
        $('#ct-character').prop('disabled', true);
      });

      // Tree: a row opens its children; closing it closes everything below.
      function setOpen(row, open) {
        row.attr('aria-expanded', open ? 'true' : 'false');
        var key = row.data('key');
        var children = $('tr[data-parent="' + key + '"]');
        if (open) {
          children.not('.ct-detail').removeClass('d-none');
          if (row.hasClass('ct-hull')) showHull(row.data('hull'));
        } else {
          children.each(function () { if ($(this).attr('aria-expanded') === 'true') setOpen($(this), false); });
          children.addClass('d-none');
        }
      }
      function onActivate(selector, fn) {
        $(document).on('click', selector, function () { fn($(this)); });
        $(document).on('keydown', selector, function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fn($(this)); }
        });
      }
      onActivate('.ct-group[role="button"], .ct-segment, .ct-hull', function (row) { setOpen(row, row.attr('aria-expanded') !== 'true'); });

      function showHull(typeId) {
        var row = $('tr[data-detail-of="' + typeId + '"]').removeClass('d-none');
        var cell = row.children('td');
        if (hulls[typeId]) return;
        cell.empty().append(el('div', 'p-2 pl-5 text-muted', tr('loading')));
        $.getJSON(urls.hull + typeId, query).done(function (data) {
          hulls[typeId] = data;
          renderHull(typeId);
        }).fail(function (xhr) {
          cell.empty().append(el('div', 'p-2 pl-5 text-danger', (xhr.responseJSON && xhr.responseJSON.message) || tr('error')));
        });
      }

      function renderHull(typeId) {
        var data = hulls[typeId];
        var cell = $('tr[data-detail-of="' + typeId + '"]').children('td').empty();
        var box = el('div', 'ct-chart-box');
        var canvas = $('<canvas></canvas>');
        box.append(canvas);
        cell.append(box);
        var estimated = data.series.filter(function (d) { return d.estimated && d.open; });
        var note = tr('chart_note');
        if (estimated.length) note += ' ' + tr('chart_estimated', { date: data.collection_start });
        cell.append(el('div', 'ct-chart-note', note));
        drawChart(canvas[0], data.series);
        cell.append(contractsTable(typeId));
      }

      function drawChart(canvas, series) {
        if (typeof Chart === 'undefined') return;
        var dashedUntil = series.findIndex(function (d) { return !d.estimated; });
        new Chart(canvas.getContext('2d'), {
          type: 'line',
          data: {
            labels: series.map(function (d) { return d.date; }),
            datasets: [
              {
                label: tr('chart_median'), data: series.map(function (d) { return d.median; }),
                borderColor: '#007bff', backgroundColor: 'rgba(0, 123, 255, .08)', fill: false, pointRadius: 0, borderWidth: 2, spanGaps: true,
                // Days before the first run are drawn lighter (estimated).
                pointBackgroundColor: series.map(function (d) { return d.estimated ? '#9ec5fe' : '#007bff'; })
              },
              {
                label: tr('chart_lowest'), data: series.map(function (d) { return d.lowest; }),
                borderColor: '#28a745', borderDash: [4, 3], fill: false, pointRadius: 0, borderWidth: 1.5, spanGaps: true
              },
              {
                label: tr('chart_sold'), data: series.map(function (d) { return d.sold_median; }),
                borderColor: '#fd7e14', backgroundColor: '#fd7e14', showLine: false,
                pointRadius: series.map(function (d) { return d.sold ? Math.min(3 + d.sold, 8) : 0; })
              }
            ]
          },
          options: {
            responsive: true, maintainAspectRatio: false, animation: false,
            legend: { position: 'bottom', labels: { boxWidth: 12 } },
            tooltips: {
              mode: 'index', intersect: false,
              callbacks: {
                label: function (item, d) {
                  var day = series[item.index], ds = item.datasetIndex;
                  if (item.yLabel === null || item.yLabel === undefined || isNaN(item.yLabel)) return null;
                  var extra = ds === 0 ? ' (' + tr('chart_open', { count: day.open }) + ')' : (ds === 2 ? ' (' + tr('chart_sold_count', { count: day.sold }) + ')' : '');
                  return d.datasets[ds].label + ': ' + isk(item.yLabel) + extra + (day.estimated && ds === 0 ? ' ≈' : '');
                }
              }
            },
            scales: {
              xAxes: [{ ticks: { maxTicksLimit: 8, maxRotation: 0 }, gridLines: { display: false } }],
              yAxes: [{ ticks: { callback: function (v) { return isk(v); }, maxTicksLimit: 5 } }]
            }
          },
          plugins: [{
            // Shade the estimated days before the first run.
            beforeDraw: function (chart) {
              if (dashedUntil <= 0) return;
              var x = chart.scales['x-axis-0'], y = chart.chartArea, ctx = chart.ctx;
              var right = x.getPixelForValue(null, Math.min(dashedUntil, series.length - 1));
              ctx.save();
              ctx.fillStyle = 'rgba(108, 117, 125, .08)';
              ctx.fillRect(y.left, y.top, right - y.left, y.bottom - y.top);
              ctx.restore();
            }
          }]
        });
      }

      var COLUMNS = [
        { key: 'net_price', label: 'col_ship_price', cls: 'text-right' },
        { key: 'asked', label: 'col_price', cls: 'text-right' },
        { key: 'extras_value', label: 'col_fitting', cls: 'text-right', title: 'fitting_help' },
        { key: 'security', label: 'col_location' },
        { key: 'issuer', label: 'col_issuer' },
        { key: 'days_left', label: 'col_expires' },
        { key: null, label: null }
      ];
      function value(c, key) {
        if (key === 'asked') return c.type === 'auction' ? c.buyout : c.price;
        if (key === 'issuer') return c.issuer ? c.issuer.toLowerCase() : null;
        return c[key];
      }
      function sorted(list) {
        return list.slice().sort(function (a, b) {
          var x = value(a, sort.key), y = value(b, sort.key);
          var xe = x === null || x === undefined, ye = y === null || y === undefined;
          if (xe || ye) return xe === ye ? a.contract_id - b.contract_id : (xe ? 1 : -1);
          var c = typeof x === 'string' ? x.localeCompare(y) : x - y;
          return c * sort.dir || a.contract_id - b.contract_id;
        });
      }

      function contractsTable(typeId) {
        var data = hulls[typeId];
        var table = el('table', 'table table-sm mb-0 ct-sub');
        var head = $('<tr></tr>');
        COLUMNS.forEach(function (col) {
          var th = el('th', col.cls || '');
          if (col.key) {
            var a = el('a', 'ct-sort', tr(col.label)).attr({ href: '#', title: tr(col.title || 'sort_help') });
            if (sort.key === col.key) {
              a.append(' ', el('i', 'fas fa-sort-' + (sort.dir === 1 ? 'up' : 'down')));
              th.attr('aria-sort', sort.dir === 1 ? 'ascending' : 'descending');
            }
            a.on('click', function (e) {
              e.preventDefault();
              sort = sort.key === col.key ? { key: col.key, dir: -sort.dir } : { key: col.key, dir: 1 };
              try { localStorage.setItem(SORT_KEY, JSON.stringify(sort)); } catch (err) { /* ignore */ }
              Object.keys(hulls).forEach(function (id) { if ($('tr[data-detail-of="' + id + '"]').children('td').children('table').length) replaceTable(id); });
            });
            th.append(a);
          }
          head.append(th);
        });
        table.append($('<thead></thead>').append(head));
        var body = $('<tbody></tbody>');
        if (!data.contracts.length) body.append($('<tr></tr>').append(el('td', 'text-muted', tr('no_contracts')).attr('colspan', 7)));
        sorted(data.contracts).forEach(function (c) { body.append(contractRow(c)); });
        return table.append(body);
      }
      function replaceTable(typeId) {
        var cell = $('tr[data-detail-of="' + typeId + '"]').children('td');
        cell.children('table').replaceWith(contractsTable(typeId));
      }

      function contractRow(c) {
        var tr_ = $('<tr></tr>').toggleClass('ct-uncounted', !c.counted);
        var ship = el('td', 'text-right font-weight-bold', isk(c.net_price)).attr('title', full(c.net_price));
        if (c.outlier) ship.append(badge('secondary', tr('outlier'), tr('outlier_help')));
        if (c.valuation === 'multiple') ship.append(badge('info', tr('multiple'), tr('multiple_help')));
        if (c.valuation === 'requests') ship.append(badge('info', tr('requests'), tr('requests_help')));
        tr_.append(ship);

        var asked = c.type === 'auction' ? c.buyout : c.price;
        var price = el('td', 'text-right').attr('title', full(asked));
        if (c.type === 'auction') price.append(badge('light', tr('auction'), tr('auction_help', { bid: isk(c.price) })), ' ');
        tr_.append(price.append(document.createTextNode(isk(asked))));

        var fit = el('td', 'text-right', c.extras_value ? isk(c.extras_value) : '–').attr('title', full(c.extras_value));
        if (c.extras_rigs) fit.append(badge('light', tr('rigs', { count: c.extras_rigs }), tr('rigs_help')));
        if (c.extras_unpriced) fit.append(badge('warning', tr('unpriced', { count: c.extras_unpriced }), tr('unpriced_help')));
        tr_.append(fit);

        var loc = el('td', 'ct-wrap');
        if (c.security !== null) {
          var s = Math.round(c.security * 10) / 10;
          loc.append(el('span', c.security >= 0.45 ? 'ct-high' : (c.security > 0 ? 'ct-low' : 'ct-null'), s.toFixed(1)), ' ');
        }
        loc.append(document.createTextNode(c.location || (c.structure ? tr('unknown_structure') : tr('unknown_location'))));
        loc.append(el('div', 'ct-sub-text', [c.system, c.region].filter(Boolean).join(' · ')));
        tr_.append(loc);

        var who = el('td', 'ct-wrap', c.issuer || '–');
        if (c.title) who.append(el('div', 'ct-sub-text', c.title));
        tr_.append(who);

        var days = c.days_left === 0 ? tr('expires_today') : (c.days_left === 1 ? tr('one_day_left') : tr('days_left', { days: c.days_left }));
        tr_.append(el('td', '', c.expires).attr('title', tr('issued', { date: c.issued_date })).append(el('div', 'ct-sub-text', days)));

        var actions = el('td', 'text-right text-nowrap');
        actions.append(el('button', 'btn btn-xs btn-outline-secondary ct-items', tr('contents')).attr({ type: 'button', 'data-contract': c.contract_id }), ' ');
        actions.append(el('button', 'btn btn-xs btn-primary ct-open').attr({ type: 'button', 'data-contract': c.contract_id, title: tr('open_in_game_help') })
          .append(el('i', 'fas fa-external-link-alt'), ' ' + tr('open_in_game')));
        tr_.append(actions);

        var items = $('<tr class="ct-items-row d-none"></tr>').attr('data-items-of', c.contract_id).append(el('td', 'ct-items-cell').attr('colspan', 7));
        return tr_.add(items);
      }

      $(document).on('click', '.ct-items', function () {
        var id = $(this).data('contract');
        var row = $('tr[data-items-of="' + id + '"]');
        if (!row.hasClass('d-none')) { row.addClass('d-none'); return; }
        row.removeClass('d-none');
        var cell = row.children('td');
        if (cell.data('loaded')) return;
        cell.text(tr('loading'));
        $.getJSON(urls.items + id).done(function (data) {
          var list = el('ul', 'ct-items-list');
          data.items.forEach(function (i) {
            var name = i.quantity + ' × ' + i.name;
            if (i.bpc) name += ' (' + tr('bpc', { runs: i.runs, me: i.me, te: i.te }) + ')';
            var li = el('li').toggleClass('ct-wanted', !i.included).toggleClass('ct-rig', i.rig);
            li.append(el('span', '', name + (i.included ? '' : ' – ' + tr('wanted'))));
            li.append(el('span', 'text-muted', i.capital ? '' : (i.rig ? tr('rig_fitted') : isk(i.value))));
            list.append(li);
          });
          cell.empty().append(data.items.length ? list : el('span', '', tr('no_items')));
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
