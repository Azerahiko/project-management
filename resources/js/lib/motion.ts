/**
 * Shared motion helper.
 *
 * `prefersReducedMotion()` is checked before every GSAP tween so users who
 * opted out of motion get instant state changes instead of animations.
 */
export function prefersReducedMotion(): boolean {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return false;
    }

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
