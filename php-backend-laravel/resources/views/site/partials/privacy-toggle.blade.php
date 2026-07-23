{{-- One privacy switch. The whole row is the label, so tapping anywhere toggles it
     (the app's ToggleRow makes the entire row clickable). --}}
<label class="aprof-toggle">
    <span class="aprof-toggle__text">
        <b>{{ $title }}</b>
        <small>{{ $description }}</small>
    </span>
    <input type="checkbox" name="{{ $name }}" value="1" @checked($checked)>
    <span class="aprof-toggle__track" aria-hidden="true"><span class="aprof-toggle__thumb"></span></span>
</label>
