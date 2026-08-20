const NEUTRAL = 'bg-slate-100 text-slate-500 ring-slate-200'

/** A nota chega como string de escala fixa (1.00–4.00); só é convertida para escolher a cor. */
function toneFor(value: number): string {
  if (value < 2) return 'bg-red-100 text-red-800 ring-red-200'
  if (value < 3) return 'bg-amber-100 text-amber-800 ring-amber-200'
  if (value < 3.5) return 'bg-lime-100 text-lime-800 ring-lime-200'

  return 'bg-emerald-100 text-emerald-800 ring-emerald-200'
}

export function ScoreBadge({ score }: { score: string | null }) {
  const value = score === null ? Number.NaN : Number(score)
  // NaN cobre nota ausente ou malformada: cai no cinza neutro, nunca na cor de nota máxima.
  const tone = Number.isNaN(value) ? NEUTRAL : toneFor(value)

  return (
    <span
      className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums ring-1 ${tone}`}
    >
      {score ?? 'sem avaliação'}
    </span>
  )
}
