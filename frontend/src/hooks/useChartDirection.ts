import { useTranslation } from 'react-i18next'
import { isRtl } from '@/lib/i18n'

/**
 * SVG `text-anchor="start"/"end"` is direction-relative per the CSS
 * Writing Modes spec — under an ambient `dir="rtl"` (set on <html> by
 * this app's i18n setup), "start" anchors to the visual *right* and text
 * grows leftward, the mirror image of what recharts' own tick-positioning
 * math assumes (it's written assuming "start" always means visual left).
 * The result without this: every axis tick label silently renders shifted
 * in the wrong direction. Forcing `direction: ltr` on the chart's own
 * container resets text-anchor to the absolute (non-relative) meaning
 * recharts expects, so its layout math is correct again — the actual
 * RTL mirroring is still done deliberately via `horizontalAxisProps`/
 * `startOrientation` below, which are plain numeric/enum props recharts
 * computes independently of CSS direction.
 */
export const CHART_LTR_STYLE = { direction: 'ltr' } as const

/**
 * Recharts lays out every chart LTR internally regardless of the document's
 * `dir` — CSS direction doesn't reach into its SVG coordinate math. Without
 * this, an RTL language (Arabic, Urdu) would render every bar/line chart
 * mirrored against the surrounding page: the category axis still pinned to
 * the left, values still flowing left-to-right, while every other on-page
 * label reads right-to-left. Spread `horizontalAxisProps` onto the
 * categorical XAxis and use `startOrientation` on the numeric YAxis (the
 * axis that should sit on the reading direction's start side) to mirror
 * the whole chart to match.
 */
export function useChartDirection() {
  const { i18n } = useTranslation()
  const rtl = isRtl(i18n.language)

  return {
    rtl,
    horizontalAxisProps: { reversed: rtl },
    startOrientation: (rtl ? 'right' : 'left') as 'left' | 'right',
  }
}
