@extends('site.layout')

@section('content')
{{-- The post-event review page. Reached from a WhatsApp link by someone standing
     outside a venue on a phone, so it is deliberately one screen with one decision:
     tap a star. The comment box is optional and secondary — demanding prose is how
     you get "good" typed a thousand times. --}}
<div class="rvw">
    @if($existing !== null || session('review_saved'))
        <div class="rvw__done">
            <div class="rvw__tick" aria-hidden="true">&#10003;</div>
            <h1 class="rvw__title">Thanks for the feedback</h1>
            <p class="rvw__lede">
                Your rating for <strong>{{ $title }}</strong> has been shared with the organiser.
            </p>
            @if($existing !== null)
                <div class="rvw__stars rvw__stars--static" aria-label="You rated {{ $existing->rating }} out of 5">
                    @for($i = 1; $i <= 5; $i++)
                        <span class="rvw__star {{ $i <= $existing->rating ? 'is-on' : '' }}">&#9733;</span>
                    @endfor
                </div>
                @if(filled($existing->text))
                    <p class="rvw__quote">{{ $existing->text }}</p>
                @endif
            @endif
            <a class="rvw__link" href="{{ url('/') }}">Back to Haraan</a>
        </div>
    @elseif($tooEarly)
        <div class="rvw__done">
            <h1 class="rvw__title">Not just yet</h1>
            <p class="rvw__lede">
                You can review <strong>{{ $title }}</strong> once it has taken place.
                We'll send you this link again afterwards.
            </p>
            <a class="rvw__link" href="{{ url('/') }}">Back to Haraan</a>
        </div>
    @else
        <h1 class="rvw__title">How was it?</h1>
        <p class="rvw__lede">{{ $title }}</p>

        @if(session('review_error'))
            <div class="rvw__error">{{ session('review_error') }}</div>
        @endif
        @error('rating')<div class="rvw__error">{{ $message }}</div>@enderror

        <form method="POST" action="{{ route('review.store', $booking->ticket_code) }}" class="rvw__form">
            @csrf

            {{-- Radios, not JavaScript: the rating must submit on a dead connection
                 in a venue car park. The stars are labels over hidden inputs, so
                 keyboard and screen readers get a real radio group. --}}
            <fieldset class="rvw__rating">
                <legend class="rvw__legend">Your rating</legend>
                <div class="rvw__stars">
                    @for($i = 5; $i >= 1; $i--)
                        <input type="radio" name="rating" id="rating-{{ $i }}" value="{{ $i }}"
                               class="rvw__radio" required @checked(old('rating') == $i)>
                        <label for="rating-{{ $i }}" class="rvw__star"
                               title="{{ $i }} out of 5">
                            <span class="sr-only">{{ $i }} star{{ $i === 1 ? '' : 's' }}</span>
                            &#9733;
                        </label>
                    @endfor
                </div>
            </fieldset>

            <label class="rvw__label" for="text">Anything you'd like to add? <span>(optional)</span></label>
            <textarea class="rvw__text" name="text" id="text" rows="4" maxlength="1000"
                      placeholder="What worked, what didn't">{{ old('text') }}</textarea>

            <button type="submit" class="rvw__submit">Send rating</button>
        </form>

        <p class="rvw__note">Your name is shown with the review. Booking {{ $booking->ticket_code }}.</p>
    @endif
</div>

<style>
/* Scoped to .rvw — this page is reached from outside the site and should not
   inherit surprises from whatever the shell is doing that week. */
.rvw{max-width:520px;margin:0 auto;padding:32px 20px 64px}
.rvw__title{font-size:28px;line-height:1.2;font-weight:700;margin:0 0 6px}
.rvw__lede{font-size:16px;opacity:.75;margin:0 0 28px}
.rvw__form{display:block}
.rvw__rating{border:0;padding:0;margin:0 0 24px}
.rvw__legend{font-size:13px;text-transform:uppercase;letter-spacing:.06em;opacity:.6;margin-bottom:10px}
/* Reverse row order so the CSS sibling selector can light up every star to the
   LEFT of the hovered one, which is how a 5-star control is expected to feel. */
.rvw__stars{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:6px}
.rvw__radio{position:absolute;opacity:0;width:0;height:0}
.rvw__star{font-size:40px;line-height:1;cursor:pointer;color:#d4d4d8;transition:color .12s}
.rvw__radio:checked ~ .rvw__star,
.rvw__star:hover,.rvw__star:hover ~ .rvw__star{color:#f59e0b}
.rvw__radio:focus-visible + .rvw__star{outline:2px solid #0EA5E9;outline-offset:3px;border-radius:4px}
.rvw__stars--static{flex-direction:row;justify-content:center;margin:16px 0}
.rvw__stars--static .rvw__star{cursor:default;font-size:28px}
.rvw__stars--static .rvw__star.is-on{color:#f59e0b}
.rvw__label{display:block;font-size:14px;font-weight:600;margin:0 0 8px}
.rvw__label span{font-weight:400;opacity:.6}
.rvw__text{width:100%;padding:12px 14px;border:1px solid rgba(120,120,130,.35);border-radius:12px;
  font:inherit;background:transparent;color:inherit;resize:vertical}
.rvw__text:focus{outline:2px solid #0EA5E9;outline-offset:1px;border-color:transparent}
.rvw__submit{margin-top:20px;width:100%;padding:15px 20px;border:0;border-radius:12px;
  background:#0EA5E9;color:#fff;font-size:16px;font-weight:600;cursor:pointer}
.rvw__submit:hover{background:#0284c7}
.rvw__note{margin-top:18px;font-size:13px;opacity:.55}
.rvw__error{padding:12px 14px;border-radius:10px;background:rgba(220,38,38,.1);
  color:#dc2626;font-size:14px;margin-bottom:18px}
.rvw__done{text-align:center;padding-top:24px}
.rvw__tick{width:56px;height:56px;margin:0 auto 18px;border-radius:50%;background:#16a34a;
  color:#fff;font-size:28px;line-height:56px}
.rvw__quote{margin:14px 0 0;font-size:15px;opacity:.75;font-style:italic}
.rvw__link{display:inline-block;margin-top:26px;font-size:15px;color:#0EA5E9;text-decoration:none}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;
  clip:rect(0,0,0,0);white-space:nowrap;border:0}
</style>
@endsection
