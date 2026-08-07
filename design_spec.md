---
name: Monolith UI
colors:
  surface: '#f9f9f9'
  surface-dim: '#dadada'
  surface-bright: '#f9f9f9'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f3f3'
  surface-container: '#eeeeee'
  surface-container-high: '#e8e8e8'
  surface-container-highest: '#e2e2e2'
  on-surface: '#1a1c1c'
  on-surface-variant: '#4c4546'
  inverse-surface: '#2f3131'
  inverse-on-surface: '#f1f1f1'
  outline: '#7e7576'
  outline-variant: '#cfc4c5'
  surface-tint: '#5e5e5e'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#1b1b1b'
  on-primary-container: '#848484'
  inverse-primary: '#c6c6c6'
  secondary: '#5e5e5e'
  on-secondary: '#ffffff'
  secondary-container: '#e3e2e2'
  on-secondary-container: '#646464'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#1b1b1b'
  on-tertiary-container: '#848484'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e2e2e2'
  primary-fixed-dim: '#c6c6c6'
  on-primary-fixed: '#1b1b1b'
  on-primary-fixed-variant: '#474747'
  secondary-fixed: '#e3e2e2'
  secondary-fixed-dim: '#c7c6c6'
  on-secondary-fixed: '#1b1c1c'
  on-secondary-fixed-variant: '#464747'
  tertiary-fixed: '#e2e2e2'
  tertiary-fixed-dim: '#c6c6c6'
  on-tertiary-fixed: '#1b1b1b'
  on-tertiary-fixed-variant: '#474747'
  background: '#f9f9f9'
  on-background: '#1a1c1c'
  surface-variant: '#e2e2e2'
typography:
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '600'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1.2'
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '500'
    lineHeight: '1.1'
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.2'
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  container-max-width: 1440px
  sidebar-width: 260px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 40px
  stack-sm: 8px
  stack-md: 16px
  stack-lg: 32px
---

## Brand & Style

This design system is built on the principles of **technical minimalism**. It prioritizes information density and clarity without sacrificing elegance. The brand personality is clinical, precise, and authoritative, designed specifically for high-level technical analysis and data visualization.

The visual direction adheres to a **Strict Monochrome Minimalism**. By removing the distraction of color, we elevate the importance of form, hierarchy, and spatial relationships. The aesthetic evokes a sense of "premium utility"—where every pixel serves a purpose. The emotional response is one of calm control and professional focus.

## Colors

The palette is strictly achromatic. To maintain accessibility and hierarchy without color, we rely on a granular range of grayscale values.

- **Foundations:** Pure `#FFFFFF` surfaces sit atop a `#FBFBFB` background.
- **Accents:** In a monochrome system, the "primary" color is absolute black `#000000`, used for critical actions and primary headings.
- **Data Visualization:** Charts must use varying shades of gray, stroke weights, and patterns (dashes/dots) to differentiate data series. 
- **States:** Hover states should utilize subtle shifts in gray (e.g., `#F5F5F5` to `#EDEDED`) rather than hue changes.

## Typography

Using **Inter** exclusively, the system leverages weight and tracking to create distinction. 

- **Headlines:** Use tighter letter-spacing and semi-bold weights to create a "locked-in" technical feel.
- **Labels:** Small labels and metadata should use uppercase with slight tracking (`0.02em`) to enhance legibility at small sizes.
- **Hierarchy:** Contrast is achieved by pairing pure black (`#000000`) bold headers with medium-gray (`#737373`) regular body text.

## Layout & Spacing

The layout utilizes a **Fixed-Fluid Hybrid** model. The sidebar remains fixed at 260px, while the main content area expands within a maximum width of 1440px.

- **Grid:** A 12-column system for the dashboard main stage.
- **Whitespace:** Use "Generous Breathing Room." Components should be separated by a minimum of `32px` (`stack-lg`) to maintain the high-end minimalist aesthetic.
- **Responsive Behavior:** On tablet, the sidebar collapses into a drawer. On mobile, the 12-column grid reflows into a single column with `16px` outer margins.

## Elevation & Depth

In the absence of color, depth is the primary tool for communicating interactivity and hierarchy.

- **The Ground:** The base application background is a very light gray (`#FBFBFB`).
- **Cards & Surfaces:** Primary containers are pure white with a `1px` border of `#E5E5E5`.
- **Soft Shadows:** Elevation is suggested through "Soft Ambient" shadows. Use a double-shadow technique: 
  1. A sharp `1px` border for definition.
  2. A wide, low-opacity shadow (e.g., `box-shadow: 0 10px 30px rgba(0,0,0,0.04)`).
- **Active States:** Active elements (like a selected sidebar item) should not use a shadow; instead, use a subtle light-gray fill (`#F5F5F5`) or a black vertical indicator line.

## Shapes

The design system uses a "Soft" radius profile to balance the clinical nature of the monochrome palette.

- **Base Radius:** Elements like input fields and buttons use `4px` (`0.25rem`).
- **Container Radius:** Larger components like dashboard cards and metric blocks use `8px` (`rounded-lg`).
- **Icons:** Should be stroke-based (2px weight) with slight corner rounding to match the UI language.

## Components

### Metric Cards
Metric cards should feature a large semi-bold value in `#000000`. Labels are placed above in `label-md` style using `#737373`. Trend indicators (up/down) are represented by simple arrows without color—relying purely on orientation and percentage text.

### Sidebar
The sidebar is the anchor of the application. It uses a light gray background (`#F9F9F9`) to distinguish it from the white main stage. Navigation items use `body-md` with `12px` of vertical padding. The active state is indicated by a bold weight change and a small 2px black bar on the left edge.

### Charts
- **Lines:** Use a solid 2px black line for the primary data series. 
- **Area:** Use a light gray fill (`#F5F5F5`) under the line with a `0.5` opacity.
- **Grids:** Horizontal grid lines should be extremely subtle (`#F0F0F0`).

### Buttons
- **Primary:** Solid black background with white text.
- **Secondary:** White background with a `1px` `#E5E5E5` border and black text.
- **Ghost:** No border or background; text turns from `#737373` to `#000000` on hover.

### Inputs
Search bars and text fields use a subtle `#F5F5F5` background with no border. On focus, they transition to a white background with a `1px` black border.
