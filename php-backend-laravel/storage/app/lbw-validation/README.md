# LBW review validation set

Real footage of real appeals, annotated by a person who watched them. Nothing else
produces a number worth having.

## Adding a case

1. Drop the clip in `clips/`.
2. Add an entry to `dataset.json`.
3. Fill `expected` **only** with what a human actually judged. Leave a field out rather
   than guessing — an unannotated field is reported as unscored, which is honest; a
   guessed one silently corrupts the accuracy figure.

## Running

```
php artisan lbw:validate            # scores against dataset.json
php artisan lbw:validate --json     # also writes a timestamped report
php artisan lbw:validate --limit=5  # a quick smoke run
```

## Reading the result

Four outcomes per factor, not two:

- `hit` — model agreed with the human.
- `miss` — model disagreed. **The only outcome that means the system is wrong.**
- `abstained` — model said `cannot_tell` where the human could tell. Unhelpful, not wrong.
- `absent` — factor missing from the response entirely.

`abstained` being high means the pipeline is too blind to be useful yet. `miss` being
non-zero — especially with `certain: true` — means it is not safe to build geometry or a
decision engine on top, whatever the hit rate says.
