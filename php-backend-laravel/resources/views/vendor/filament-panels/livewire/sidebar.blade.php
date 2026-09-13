@php
    $navigation = filament()->getNavigation();
    $isRtl = __('filament-panels::layout.direction') === 'rtl';
    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
    $isSidebarFullyCollapsibleOnDesktop = filament()->isSidebarFullyCollapsibleOnDesktop();
    $hasNavigation = filament()->hasNavigation();
    $hasTopbar = filament()->hasTopbar();
@endphp

<div>
    {{-- format-ignore-start --}}
    <aside
        x-data="{}"
        @if ($isSidebarCollapsibleOnDesktop || $isSidebarFullyCollapsibleOnDesktop)
            x-cloak
        @else
            x-cloak="-lg"
        @endif
        x-bind:class="{ 'fi-sidebar-open': $store.sidebar.isOpen }"
        class="fi-sidebar fi-main-sidebar hrn-linear-sidebar fi-sidebar-open"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_START) }}

        {{-- Premium Workspace Identity Panel (Exact Spec) --}}
        <div class="fi-sidebar-header-ctn hrn-ws-header-ctn">
            <header class="fi-sidebar-header hrn-ws-panel" x-data="{ switcherOpen: false }" x-on:keydown.escape.window="switcherOpen = false">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_LOGO_BEFORE) }}

                <div class="hrn-ws-card-wrapper hrn-ws-identity-container">
                    <button
                        type="button"
                        x-on:click="switcherOpen = !switcherOpen"
                        class="hrn-ws-card hrn-ws-identity-trigger"
                        x-bind:aria-expanded="switcherOpen"
                        x-bind:class="{ 'hrn-ws-card-active hrn-ws-active': switcherOpen }"
                        title="Switch workspace (Haraan — Enterprise Control Center)"
                    >
                        {{-- Top Row: Living Branded Tile + Typography + Far Right Switch Chevron --}}
                        <div class="hrn-ws-top-row">
                            {{-- Living Brand Element: Ambient Emerald Aura + Dynamic Tile --}}
                            <div class="hrn-living-brand-container">
                                <div class="hrn-logo-aura" aria-hidden="true"></div>
                                <div class="hrn-ws-tile hrn-living-tile">
                                    <svg viewBox="190 130 360 410" class="hrn-ws-tile-svg" role="img" aria-hidden="true" fill="currentColor">
                                        <g class="hrn-anchor-symbol" fill="currentColor">
                                            <path class="hrn-h-pillar-tl" d="M216.747559,333.770569 C209.413040,344.558746 202.309097,355.077271 195.572937,365.051239 C195.572937,289.187988 195.572937,212.376709 195.572937,135.283478 C223.466293,135.283478 251.025284,135.283478 279.181854,135.283478 C279.181854,137.358246 279.181366,139.138351 279.181915,140.918472 C279.196411,188.900665 279.165955,236.882996 279.313385,284.864777 C279.324860,288.601318 278.266541,290.300659 274.711426,291.731506 C252.039734,300.856293 232.979736,315.094482 216.747559,333.770569 z" />
                                            <path class="hrn-h-bridge" d="M463.967865,135.273163 C483.623108,135.191925 503.278320,135.102341 522.933594,135.033676 C530.409790,135.007553 537.886169,135.029449 545.599487,135.029449 C545.820801,136.053864 546.129456,136.816681 546.129700,137.579575 C546.140137,166.240860 546.765137,194.919830 545.977783,223.559860 C544.193726,288.455261 501.189178,343.817047 438.773285,362.923248 C421.259521,368.284393 403.380005,370.643585 385.126587,370.868561 C370.814667,371.044952 356.378052,370.350189 342.219788,371.979675 C302.374481,376.565582 279.533875,402.422699 279.119171,442.616516 C278.823486,471.274658 279.128723,499.938995 279.164948,528.600586 C279.168274,531.236511 279.165375,533.872498 279.165375,536.908691 C251.170105,536.908691 223.607483,536.908691 195.273911,536.908691 C195.273911,535.361938 195.274872,533.774902 195.273758,532.187866 C195.254257,504.526001 195.197021,476.864136 195.222031,449.202301 C195.291153,372.742676 247.488037,311.719421 323.390381,301.125916 C344.536469,298.174561 366.226959,298.976257 387.676971,298.404205 C401.014832,298.048492 413.902374,295.976074 425.906219,290.025696 C446.177094,279.977234 456.790009,262.865814 460.901459,241.175735 C461.304352,239.050156 461.555939,236.895889 462.390442,234.262192 C463.267212,232.246841 463.946259,230.724777 463.948822,229.201569 C464.001465,197.892181 463.977020,166.582672 463.967865,135.273163 z" />
                                            <path class="hrn-h-pillar-br" d="M464.611328,377.188782 C466.216797,379.887482 467.427368,382.607269 468.637939,385.327026 C468.215546,385.231567 467.793152,385.136078 467.370728,385.040588 C466.580475,388.116272 465.305054,391.158813 465.092468,394.273895 C464.494019,403.043488 464.022522,411.844513 464.068939,420.630157 C464.146179,435.250946 464.901550,449.868835 464.937103,464.489014 C464.977173,480.976105 464.394531,497.464233 464.377777,513.952209 C464.371277,520.349182 465.171967,526.746948 465.620544,533.374573 C468.068268,534.924561 470.476288,536.712036 474.212463,534.406799 C475.614716,533.541565 478.685944,535.893127 481.006226,535.920044 C495.167297,536.084473 509.374390,536.654846 523.475586,535.721191 C530.332764,535.267151 537.040039,531.977661 543.685486,529.607910 C544.528809,529.307129 544.938843,526.794861 544.947571,525.303711 C545.037170,509.974915 545.002441,494.645386 545.002502,479.316071 C545.002686,432.161743 545.003479,385.007416 545.002563,337.853088 C545.002441,329.462921 542.583679,327.240234 533.345703,327.492188 C533.899414,329.028015 534.413940,330.455109 534.843628,331.646790 C531.141541,332.751038 527.571838,333.815796 524.262939,334.802795 C525.752808,337.952576 526.640869,339.829987 527.528870,341.707397 C525.162781,342.357391 522.782898,343.578217 520.433899,343.521484 C517.046875,343.439667 512.857117,346.223450 512.310913,349.586884 C512.054810,351.163849 511.315948,352.662445 510.597809,354.780914 C512.871094,359.727020 511.176880,361.498535 505.057068,363.226044 C499.507263,364.792603 494.192780,367.811249 489.345428,371.045380 C485.700562,373.477264 483.295990,372.615570 480.236145,370.112091 C490.119476,362.271698 500.889038,355.220093 510.345825,346.709686 C518.433289,339.431519 525.015015,330.480286 532.841125,322.215088 C534.092590,322.183838 534.765686,322.206573 535.438843,322.229309 C534.814026,321.764404 534.189148,321.299500 533.564331,320.834595 C537.600464,313.798431 541.636536,306.762268 546.070557,299.032349 C546.070557,378.776184 546.070557,457.795227 546.070557,537.184753 C518.183167,537.184753 490.466003,537.184753 462.362946,537.184753 C462.243988,535.957825 462.015930,534.701172 462.015656,533.444519 C462.004456,483.150391 461.991302,432.856201 462.113129,382.562347 C462.117432,380.776520 463.484528,378.993927 464.611328,377.188782 M540.690613,323.131836 C540.304077,323.512085 539.917542,323.892334 539.531006,324.272583 C541.105591,324.813049 542.680176,325.353516 544.254761,325.893982 C544.387634,325.493622 544.520447,325.093231 544.653320,324.692841 C543.560059,324.210358 542.466797,323.727844 540.690613,323.131836 z" />
                                            <path class="hrn-h-part-8" d="M465.006226,377.167694 C469.747009,374.801819 474.487732,372.435944 479.890625,370.169006 C483.295990,372.615570 485.700562,373.477264 489.345428,371.045380 C494.192780,367.811249 499.507263,364.792603 505.057068,363.226044 C511.176880,361.498535 512.871094,359.727020 510.597809,354.780914 C511.315948,352.662445 512.054810,351.163849 512.310913,349.586884 C512.857117,346.223450 517.046875,343.439667 520.433899,343.521484 C522.782898,343.578217 525.162781,342.357391 527.528870,341.707397 C526.640869,339.829987 525.752808,337.952576 524.262939,334.802795 C527.571838,333.815796 531.141541,332.751038 534.843628,331.646790 C534.413940,330.455109 533.899414,329.028015 533.345703,327.492188 C542.583679,327.240234 545.002441,329.462921 545.002563,337.853088 C545.003479,385.007416 545.002686,432.161743 545.002502,479.316071 C545.002441,494.645386 545.037170,509.974915 544.947571,525.303711 C544.938843,526.794861 544.528809,529.307129 543.685486,529.607910 C537.040039,531.977661 530.332764,535.267151 523.475586,535.721191 C509.374390,536.654846 495.167297,536.084473 481.006226,535.920044 C478.685944,535.893127 475.614716,533.541565 474.212463,534.406799 C470.476288,536.712036 468.068268,534.924561 465.620544,533.374573 C465.171967,526.746948 464.371277,520.349182 464.377777,513.952209 C464.394531,497.464233 464.977173,480.976105 464.937103,464.489014 C464.901550,449.868835 464.146179,435.250946 464.068939,420.630157 C464.022522,411.844513 464.494019,403.043488 465.092468,394.273895 C465.305054,391.158813 466.580475,388.116272 467.370728,385.040588 C467.793152,385.136078 468.215546,385.231567 468.637939,385.327026 C467.427368,382.607269 466.216797,379.887482 465.006226,377.167694 z" />
                                            <path class="hrn-h-part-10" d="M463.582703,135.280762 C463.977020,166.582672 464.001465,197.892181 463.948822,229.201569 C463.946259,230.724777 463.267212,232.246841 462.520447,233.872452 C462.193054,202.463882 462.237122,170.952271 462.341248,139.440842 C462.345825,138.055756 462.899567,136.672470 463.582703,135.280762 z" />
                                            <path class="hrn-h-part-11" d="M533.372620,320.959534 C534.189148,321.299500 534.814026,321.764404 535.438843,322.229309 C534.765686,322.206573 534.092590,322.183838 533.120850,322.038696 C532.854431,321.601288 532.973938,321.324066 533.372620,320.959534 z" />
                                            <path class="hrn-h-part-15" d="M541.032104,323.188599 C542.466797,323.727844 543.560059,324.210358 544.653320,324.692841 C544.520447,325.093231 544.387634,325.493622 544.254761,325.893982 C542.680176,325.353516 541.105591,324.813049 539.531006,324.272583 C539.917542,323.892334 540.304077,323.512085 541.032104,323.188599 z" />
                                        </g>
                                    </svg>
                                </div>
                            </div>

                            {{-- Workspace Meta (Visible when expanded) --}}
                            <div class="hrn-ws-body" x-show="$store.sidebar.isOpen" x-transition:enter="hrn-fade-enter">
                                <span class="hrn-ws-name">Haraan</span>
                                <span class="hrn-ws-sub">Enterprise Control Center</span>
                            </div>

                            {{-- Far Right Workspace Switch Chevron --}}
                            <div class="hrn-ws-chevron-wrapper" x-show="$store.sidebar.isOpen" x-transition:enter="hrn-fade-enter">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" class="hrn-ws-chevron" x-bind:class="{ 'rotate-180': switcherOpen }">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 6l4 4 4-4" />
                                </svg>
                            </div>
                        </div>

                        {{-- Beneath That Row: Small Environment Line with "Production" Badge & Subtle Live Status Dot --}}
                        <div class="hrn-ws-env-line" x-show="$store.sidebar.isOpen" x-transition:enter="hrn-fade-enter">
                            <span class="hrn-ws-status-dot" aria-hidden="true"></span>
                            <span class="hrn-ws-env-badge">Production</span>
                        </div>
                    </button>

                    {{-- Workspace Switcher Floating Popover --}}
                    <div
                        x-show="switcherOpen"
                        x-cloak
                        x-on:click.outside="switcherOpen = false"
                        x-transition:enter="hrn-popover-enter"
                        x-transition:enter-start="hrn-popover-enter-start"
                        x-transition:enter-end="hrn-popover-enter-end"
                        x-transition:leave="hrn-popover-leave"
                        x-transition:leave-start="hrn-popover-leave-start"
                        x-transition:leave-end="hrn-popover-leave-end"
                        class="hrn-ws-popover"
                        x-bind:class="{ 'hrn-ws-popover-rail': ! $store.sidebar.isOpen }"
                    >
                        {{-- Org Card --}}
                        <div class="hrn-ws-pop-org">
                            <div class="hrn-ws-pop-org-tile">
                                <svg viewBox="190 130 360 410" class="w-5 h-5" role="img" aria-hidden="true" fill="currentColor">
                                    <g class="hrn-anchor-symbol" fill="currentColor">
                                        <path class="hrn-h-pillar-tl" d="M216.747559,333.770569 C209.413040,344.558746 202.309097,355.077271 195.572937,365.051239 C195.572937,289.187988 195.572937,212.376709 195.572937,135.283478 C223.466293,135.283478 251.025284,135.283478 279.181854,135.283478 C279.181854,137.358246 279.181366,139.138351 279.181915,140.918472 C279.196411,188.900665 279.165955,236.882996 279.313385,284.864777 C279.324860,288.601318 278.266541,290.300659 274.711426,291.731506 C252.039734,300.856293 232.979736,315.094482 216.747559,333.770569 z" />
                                        <path class="hrn-h-bridge" d="M463.967865,135.273163 C483.623108,135.191925 503.278320,135.102341 522.933594,135.033676 C530.409790,135.007553 537.886169,135.029449 545.599487,135.029449 C545.820801,136.053864 546.129456,136.816681 546.129700,137.579575 C546.140137,166.240860 546.765137,194.919830 545.977783,223.559860 C544.193726,288.455261 501.189178,343.817047 438.773285,362.923248 C421.259521,368.284393 403.380005,370.643585 385.126587,370.868561 C370.814667,371.044952 356.378052,370.350189 342.219788,371.979675 C302.374481,376.565582 279.533875,402.422699 279.119171,442.616516 C278.823486,471.274658 279.128723,499.938995 279.164948,528.600586 C279.168274,531.236511 279.165375,533.872498 279.165375,536.908691 C251.170105,536.908691 223.607483,536.908691 195.273911,536.908691 C195.273911,535.361938 195.274872,533.774902 195.273758,532.187866 C195.254257,504.526001 195.197021,476.864136 195.222031,449.202301 C195.291153,372.742676 247.488037,311.719421 323.390381,301.125916 C344.536469,298.174561 366.226959,298.976257 387.676971,298.404205 C401.014832,298.048492 413.902374,295.976074 425.906219,290.025696 C446.177094,279.977234 456.790009,262.865814 460.901459,241.175735 C461.304352,239.050156 461.555939,236.895889 462.390442,234.262192 C463.267212,232.246841 463.946259,230.724777 463.948822,229.201569 C464.001465,197.892181 463.977020,166.582672 463.967865,135.273163 z" />
                                        <path class="hrn-h-pillar-br" d="M464.611328,377.188782 C466.216797,379.887482 467.427368,382.607269 468.637939,385.327026 C468.215546,385.231567 467.793152,385.136078 467.370728,385.040588 C466.580475,388.116272 465.305054,391.158813 465.092468,394.273895 C464.494019,403.043488 464.022522,411.844513 464.068939,420.630157 C464.146179,435.250946 464.901550,449.868835 464.937103,464.489014 C464.977173,480.976105 464.394531,497.464233 464.377777,513.952209 C464.371277,520.349182 465.171967,526.746948 465.620544,533.374573 C468.068268,534.924561 470.476288,536.712036 474.212463,534.406799 C475.614716,533.541565 478.685944,535.893127 481.006226,535.920044 C495.167297,536.084473 509.374390,536.654846 523.475586,535.721191 C530.332764,535.267151 537.040039,531.977661 543.685486,529.607910 C544.528809,529.307129 544.938843,526.794861 544.947571,525.303711 C545.037170,509.974915 545.002441,494.645386 545.002502,479.316071 C545.002686,432.161743 545.003479,385.007416 545.002563,337.853088 C545.002441,329.462921 542.583679,327.240234 533.345703,327.492188 C533.899414,329.028015 534.413940,330.455109 534.843628,331.646790 C531.141541,332.751038 527.571838,333.815796 524.262939,334.802795 C525.752808,337.952576 526.640869,339.829987 527.528870,341.707397 C525.162781,342.357391 522.782898,343.578217 520.433899,343.521484 C517.046875,343.439667 512.857117,346.223450 512.310913,349.586884 C512.054810,351.163849 511.315948,352.662445 510.597809,354.780914 C512.871094,359.727020 511.176880,361.498535 505.057068,363.226044 C499.507263,364.792603 494.192780,367.811249 489.345428,371.045380 C485.700562,373.477264 483.295990,372.615570 480.236145,370.112091 C490.119476,362.271698 500.889038,355.220093 510.345825,346.709686 C518.433289,339.431519 525.015015,330.480286 532.841125,322.215088 C534.092590,322.183838 534.765686,322.206573 535.438843,322.229309 C534.814026,321.764404 534.189148,321.299500 533.564331,320.834595 C537.600464,313.798431 541.636536,306.762268 546.070557,299.032349 C546.070557,378.776184 546.070557,457.795227 546.070557,537.184753 C518.183167,537.184753 490.466003,537.184753 462.362946,537.184753 C462.243988,535.957825 462.015930,534.701172 462.015656,533.444519 C462.004456,483.150391 461.991302,432.856201 462.113129,382.562347 C462.117432,380.776520 463.484528,378.993927 464.611328,377.188782 M540.690613,323.131836 C540.304077,323.512085 539.917542,323.892334 539.531006,324.272583 C541.105591,324.813049 542.680176,325.353516 544.254761,325.893982 C544.387634,325.493622 544.520447,325.093231 544.653320,324.692841 C543.560059,324.210358 542.466797,323.727844 540.690613,323.131836 z" />
                                        <path class="hrn-h-part-8" d="M465.006226,377.167694 C469.747009,374.801819 474.487732,372.435944 479.890625,370.169006 C483.295990,372.615570 485.700562,373.477264 489.345428,371.045380 C494.192780,367.811249 499.507263,364.792603 505.057068,363.226044 C511.176880,361.498535 512.871094,359.727020 510.597809,354.780914 C511.315948,352.662445 512.054810,351.163849 512.310913,349.586884 C512.857117,346.223450 517.046875,343.439667 520.433899,343.521484 C522.782898,343.578217 525.162781,342.357391 527.528870,341.707397 C526.640869,339.829987 525.752808,337.952576 524.262939,334.802795 C527.571838,333.815796 531.141541,332.751038 534.843628,331.646790 C534.413940,330.455109 533.899414,329.028015 533.345703,327.492188 C542.583679,327.240234 545.002441,329.462921 545.002563,337.853088 C545.003479,385.007416 545.002686,432.161743 545.002502,479.316071 C545.002441,494.645386 545.037170,509.974915 544.947571,525.303711 C544.938843,526.794861 544.528809,529.307129 543.685486,529.607910 C537.040039,531.977661 530.332764,535.267151 523.475586,535.721191 C509.374390,536.654846 495.167297,536.084473 481.006226,535.920044 C478.685944,535.893127 475.614716,533.541565 474.212463,534.406799 C470.476288,536.712036 468.068268,534.924561 465.620544,533.374573 C465.171967,526.746948 464.371277,520.349182 464.377777,513.952209 C464.394531,497.464233 464.977173,480.976105 464.937103,464.489014 C464.901550,449.868835 464.146179,435.250946 464.068939,420.630157 C464.022522,411.844513 464.494019,403.043488 465.092468,394.273895 C465.305054,391.158813 466.580475,388.116272 467.370728,385.040588 C467.793152,385.136078 468.215546,385.231567 468.637939,385.327026 C467.427368,382.607269 466.216797,379.887482 465.006226,377.167694 z" />
                                        <path class="hrn-h-part-10" d="M463.582703,135.280762 C463.977020,166.582672 464.001465,197.892181 463.948822,229.201569 C463.946259,230.724777 463.267212,232.246841 462.520447,233.872452 C462.193054,202.463882 462.237122,170.952271 462.341248,139.440842 C462.345825,138.055756 462.899567,136.672470 463.582703,135.280762 z" />
                                        <path class="hrn-h-part-11" d="M533.372620,320.959534 C534.189148,321.299500 534.814026,321.764404 535.438843,322.229309 C534.765686,322.206573 534.092590,322.183838 533.120850,322.038696 C532.854431,321.601288 532.973938,321.324066 533.372620,320.959534 z" />
                                        <path class="hrn-h-part-15" d="M541.032104,323.188599 C542.466797,323.727844 543.560059,324.210358 544.653320,324.692841 C544.520447,325.093231 544.387634,325.493622 544.254761,325.893982 C542.680176,325.353516 541.105591,324.813049 539.531006,324.272583 C539.917542,323.892334 540.304077,323.512085 541.032104,323.188599 z" />
                                    </g>
                                </svg>
                            </div>
                            <div class="hrn-ws-pop-org-meta">
                                <span class="hrn-ws-pop-org-title">Haraan Entertainment</span>
                                <span class="hrn-ws-pop-org-sub">Enterprise Tier</span>
                            </div>
                            <span class="hrn-ws-pop-chip">Live</span>
                        </div>

                        <div class="hrn-ws-pop-divider"></div>

                        <div class="hrn-ws-pop-section-label">Workspaces & Consoles</div>

                        {{-- Enterprise Control Center (Current) --}}
                        <a href="{{ url('/control') }}" class="hrn-ws-pop-item hrn-ws-pop-item-active">
                            <div class="hrn-ws-pop-icon hrn-ws-pop-icon-control">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="hrn-ws-pop-text">
                                <div class="flex items-center gap-1.5">
                                    <span class="hrn-ws-pop-item-title font-semibold">Enterprise Control Center</span>
                                    <span class="hrn-ws-pop-current-pill">Current</span>
                                </div>
                                <span class="hrn-ws-pop-item-desc">Super Admin & Telemetry</span>
                            </div>
                            <kbd class="hrn-ws-kbd">^1</kbd>
                        </a>

                        {{-- Partner Console --}}
                        <a href="{{ url('/partner') }}" class="hrn-ws-pop-item">
                            <div class="hrn-ws-pop-icon">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h14M3 7l2 10h10l2-10M3 7l1-3h12l1 3M7 11v3m6-3v3" />
                                </svg>
                            </div>
                            <div class="hrn-ws-pop-text">
                                <div class="flex items-center gap-1.5">
                                    <span class="hrn-ws-pop-item-title">Partner Console</span>
                                    <span class="hrn-ws-pop-tag">Venues</span>
                                </div>
                                <span class="hrn-ws-pop-item-desc">Box Office & Organizers</span>
                            </div>
                            <kbd class="hrn-ws-kbd">^2</kbd>
                        </a>

                        {{-- Employee Workplace --}}
                        <a href="{{ url('/employee') }}" class="hrn-ws-pop-item">
                            <div class="hrn-ws-pop-icon">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" />
                                </svg>
                            </div>
                            <div class="hrn-ws-pop-text">
                                <div class="flex items-center gap-1.5">
                                    <span class="hrn-ws-pop-item-title">Employee Desk</span>
                                    <span class="hrn-ws-pop-tag">Staff</span>
                                </div>
                                <span class="hrn-ws-pop-item-desc">Shifts & HRMS</span>
                            </div>
                            <kbd class="hrn-ws-kbd">^3</kbd>
                        </a>

                        <div class="hrn-ws-pop-divider"></div>

                        {{-- Quick Operations --}}
                        <div class="hrn-ws-pop-footer">
                            <a href="{{ url('/control/command-center') }}" class="hrn-ws-pop-action">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" class="w-3.5 h-3.5 text-indigo-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M2 4h12M2 8h12M2 12h8" />
                                </svg>
                                <span>Command Center</span>
                                <kbd class="hrn-ws-kbd-micro">⌘K</kbd>
                            </a>
                            <a href="{{ url('/control/server-status') }}" class="hrn-ws-pop-action">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>Status: Healthy</span>
                            </a>
                        </div>
                    </div>
                </div>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_LOGO_AFTER) }}
            </header>

            {{-- Linear-Style Jump to / Command Bar Trigger --}}
            @if (filament()->isGlobalSearchEnabled())
                <div class="hrn-sb-search-row" x-show="$store.sidebar.isOpen" x-transition:enter="hrn-fade-enter">
                    <button
                        type="button"
                        x-data="{}"
                        x-on:click="
                            const input = document.querySelector('.fi-global-search-field input');
                            if (input) {
                                input.focus();
                            } else {
                                $dispatch('open-global-search');
                            }
                        "
                        class="hrn-sb-search-trigger"
                        title="Quick search (Ctrl + K)"
                    >
                        <svg class="hrn-sb-search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17.5 17.5l-4.5-4.5m1.5-4a6 6 0 11-12 0 6 6 0 0112 0z" />
                        </svg>
                        <span class="hrn-sb-search-placeholder">Jump to or search...</span>
                        <kbd class="hrn-sb-kbd">
                            <span class="text-[10px]">⌘</span>K
                        </kbd>
                    </button>
                </div>
            @endif
        </div>

        @if (filament()->hasTenancy() && filament()->hasTenantMenu())
            <x-filament-panels::tenant-menu />
        @endif

        {{-- Sidebar Navigation Section --}}
        <nav class="fi-sidebar-nav hrn-sb-nav">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}

            <ul class="fi-sidebar-nav-groups hrn-sb-groups">
                @foreach ($navigation as $group)
                    @php
                        $isGroupActive = $group->isActive();
                        $isGroupCollapsible = $group->isCollapsible();
                        $groupIcon = $group->getIcon();
                        $groupItems = $group->getItems();
                        $groupLabel = $group->getLabel();
                        $groupExtraSidebarAttributeBag = $group->getExtraSidebarAttributeBag();
                    @endphp

                    <x-filament-panels::sidebar.group
                        :active="$isGroupActive"
                        :collapsible="$isGroupCollapsible"
                        :icon="$groupIcon"
                        :items="$groupItems"
                        :label="$groupLabel"
                        :attributes="\Filament\Support\prepare_inherited_attributes($groupExtraSidebarAttributeBag)"
                    />
                @endforeach
            </ul>

            <script>
                var collapsedGroups = JSON.parse(
                    localStorage.getItem('collapsedGroups'),
                )

                if (collapsedGroups === null || collapsedGroups === 'null') {
                    localStorage.setItem(
                        'collapsedGroups',
                        JSON.stringify(@js(
                        collect($navigation)
                            ->filter(fn (\Filament\Navigation\NavigationGroup $group): bool => $group->isCollapsed())
                            ->map(fn (\Filament\Navigation\NavigationGroup $group): string => $group->getLabel())
                            ->values()
                            ->all()
                    )),
                    )
                }

                collapsedGroups = JSON.parse(
                    localStorage.getItem('collapsedGroups'),
                )

                document
                    .querySelectorAll('.fi-sidebar-group')
                    .forEach((group) => {
                        if (
                            !collapsedGroups.includes(group.dataset.groupLabel)
                        ) {
                            return
                        }

                        // Alpine.js loads too slow, so attempt to hide a
                        // collapsed sidebar group earlier.
                        const items = group.querySelector('.fi-sidebar-group-items');
                        if (items) {
                            items.style.display = 'none';
                        }
                        group.classList.add('fi-collapsed')
                    })
            </script>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_END) }}
        </nav>

        @php
            $isAuthenticated = filament()->auth()->check();
            $hasDatabaseNotificationsInSidebar = filament()->hasDatabaseNotifications() && filament()->getDatabaseNotificationsPosition() === \Filament\Enums\DatabaseNotificationsPosition::Sidebar;
            $hasUserMenuInSidebar = filament()->hasUserMenu() && filament()->getUserMenuPosition() === \Filament\Enums\UserMenuPosition::Sidebar;
            $shouldRenderFooter = $isAuthenticated && ($hasDatabaseNotificationsInSidebar || $hasUserMenuInSidebar);
        @endphp

        @if ($shouldRenderFooter)
            <div class="fi-sidebar-footer hrn-sb-footer">
                @if ($hasDatabaseNotificationsInSidebar)
                    @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                    ])
                @endif

                @if ($hasUserMenuInSidebar)
                    <x-filament-panels::user-menu />
                @endif
            </div>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_FOOTER) }}
    </aside>
    {{-- format-ignore-end --}}

    <x-filament-actions::modals />
</div>
