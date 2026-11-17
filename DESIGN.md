# DESIGN.md — Visual Direction & Frontend Architecture

## 1. Concept: Monolith & Craft (Precision Workspace Engine)

A distinct, high-density project management interface inspired by architectural precision tools, technical telemetry displays, and editorial publishing layouts.

### Core Character
- **Architectural & Grounded:** Clean structural grid with crisp hairline borders (`1px solid var(--border)`). Eliminates generic AI-slop patterns: floating glassmorphism, muddy neon gradients, and repetitive pill-shaped buttons.
- **Editorial Contrast:** Strong geometric headings paired with dense, monospace technical metadata (task keys, status codes, dates, and metric counters).
- **Purposeful Asymmetry:** Every major view possesses a unique layout engineered for its specific operational job rather than reusing a generic 4-card metric header.
- **Micro-tactility:** 150ms spring transitions, subtle inset highlights on active states, crisp focus rings, and high-contrast dark/light mode parity.

---

## 2. Typography System

| Role | Font Family | Weight | Purpose |
|---|---|---|---|
| **Display / Heading** | `Plus Jakarta Sans` | 600, 700 | Page headers, section titles, modal titles, major metric integers |
| **Interface / Body** | `Plus Jakarta Sans` | 400, 500, 600 | Navigation links, buttons, form controls, table text, dialog content |
| **Telemetry / Data** | `JetBrains Mono` | 400, 500, 600 | Task IDs (`#TSK-102`), timestamps, percentage progress, keyboard shortcuts (`⌘K`), status badges |

---

## 3. Color & Surface Architecture

### Primary Palette: Precision Cobalt
- `primary-50`: `#eef4ff`
- `primary-500`: `#326bf5`
- `primary-600`: `#214fe6` (Interactive default)
- `primary-700`: `#193cc0` (Active / Pressed)
- `primary-900`: `#1b2f7b`

### Surface Neutrals: Architectural Slate & Zinc
- **Light Mode:**
  - Base canvas: `#f6f8fa` (Subtle warm paper gray)
  - Card / Panel background: `#ffffff`
  - Border hairline: `#e2e7ee`
  - Text primary: `#090c10` (Deep obsidian)
  - Text secondary: `#57606a`
  - Text muted: `#8c959f`
- **Dark Mode:**
  - Base canvas: `#090c10` (Pitch slate)
  - Card / Panel background: `#0f131a` (Deep obsidian panel)
  - Surface elevated: `#181d26`
  - Border hairline: `#222834`
  - Text primary: `#f0f3f6`
  - Text secondary: `#919eab`
  - Text muted: `#57606a`

### Semantic Accent Signals
- **Positive / Complete:** Emerald `#10b981` (Restricted to success states and finished tasks)
- **Pending / In Progress:** Amber `#f59e0b`
- **Critical / Overdue / Destructive:** Rose `#f43f5e`
- **Focus / Sprint Lead:** Cobalt `#214fe6`

---

## 4. Layout & Spacing Principles

1. **No Identical Metric Decks:** Metric displays are directly integrated into the context they govern (e.g., pipeline bars, inline task counters, split executive telemetries).
2. **Compact Ergonomics:** Form controls and action buttons use 6px-8px border radii with 36px-40px heights instead of oversized bubbly pills.
3. **Information Density with Hierarchy:** High-density tables and Kanban columns provide immediate at-a-glance status without scrolling past empty white margins.
4. **Equal Left and Right Edges:** Content containers maintain strict grid gutters across responsive breakpoints (1440, 1280, 768, 390, 360).

---

## 5. Page-Specific Compositions

- **Shell:** Deep charcoal sidebar with collapsible navigation groups, active highlight with high-contrast accent pip, search palette trigger (`⌘K`), and user status chip.
- **Dashboard:** Split command center: Left column features Sprint Telemetry & Velocity; Main center features Pipeline Progression & Active Project Matrix; Right column features Urgent Attention Feed & Audit Timeline.
- **Projects:** Filterable catalog featuring high-density table and grid layouts, inline team assignments, milestone completion meters, and health badges.
- **Tasks:** Hybrid command board featuring unified status swimlanes, monospace task code badges, priority flags, and rapid inline creation.
- **Users & Roles:** Tabular security matrix with explicit permission toggles and audit indicators.
- **Settings:** Two-pane configuration panel with instant feedback and clean form groups.
- **Blade & Email Templates:** Transactional templates styled to reflect the clean architectural typography and dark/light contrast.
