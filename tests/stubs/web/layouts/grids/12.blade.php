{{-- Stand-in for SeAT's layout in the tests: renders the page's sections and stacks only. --}}
<title>@yield('title')</title>
@stack('head')
@yield('full')
@stack('javascript')
