export function Panel({ children, tone = 'neutral' }: { children: string; tone?: 'neutral' | 'error' }) {
  const toneClasses =
    tone === 'error' ? 'text-red-800 ring-red-200 bg-red-50' : 'text-slate-600 ring-slate-200 bg-white'

  return <p className={`rounded-lg px-6 py-8 text-center shadow-sm ring-1 ${toneClasses}`}>{children}</p>
}
