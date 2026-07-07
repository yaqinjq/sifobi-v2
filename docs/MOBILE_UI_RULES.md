# Mobile UI Rules

## Target Devices

- iPhone 11 through iPhone 15 Pro Max
- Modern Android phones
- Desktop browser as responsive secondary target

## Layout

- Use mobile-first layouts.
- Use `viewport-fit=cover`.
- Respect safe area insets with CSS variables or Tailwind utilities.
- Operational forms should generally cap content width around `480px` to `768px` on desktop.
- Do not make staff workflows depend on wide desktop tables.

## Inputs

- Quantity inputs must use `inputmode="decimal"`.
- Numeric form text must be at least `16px` to avoid iOS zoom.
- Tap targets must be at least `44px` high.
- Place primary submit actions in a sticky bottom area when forms are long.

## Navigation

- Use bottom navigation for mobile operational areas.
- Keep labels short and scannable.
- Avoid dense menus for staff workflows.

## Components

- Use cards for repeated items, summaries, and compact operation panels.
- Avoid nested cards.
- Use clear validation states and persistent context headers.
- Keep destructive actions separated from primary submit actions.

