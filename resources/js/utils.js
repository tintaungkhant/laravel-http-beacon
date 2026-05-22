export function timeAgo(date) {
    if (!date) return ''
    const d = new Date(date)
    const seconds = Math.max(1, Math.floor((Date.now() - d.getTime()) / 1000))
    if (seconds < 60) return `${seconds}s ago`
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days < 30) return `${days}d ago`
    const months = Math.floor(days / 30)
    return `${months}mo ago`
}

function pad2(n) {
    return String(n).padStart(2, '0')
}

function formatYmdHms(d, utc = false) {
    const year = utc ? d.getUTCFullYear() : d.getFullYear()
    const month = utc ? d.getUTCMonth() : d.getMonth()
    const day = utc ? d.getUTCDate() : d.getDate()
    const hour = utc ? d.getUTCHours() : d.getHours()
    const min = utc ? d.getUTCMinutes() : d.getMinutes()
    const sec = utc ? d.getUTCSeconds() : d.getSeconds()
    return `${year}-${pad2(month + 1)}-${pad2(day)} ${pad2(hour)}:${pad2(min)}:${pad2(sec)}`
}

export function localTimezoneLabel() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Local'
    } catch {
        return 'Local'
    }
}

export function formatDateTimeLocal(date) {
    if (!date) return ''
    const d = new Date(date)
    return formatYmdHms(d, false)
}

export function formatYmdHmsLocal(date) {
    if (!date) return ''
    return formatYmdHms(new Date(date), false)
}

export function formatYmdHmsUtc(date) {
    if (!date) return ''
    return formatYmdHms(new Date(date), true)
}

export function formatDateTimeUTC(date) {
    if (!date) return ''
    const d = new Date(date)
    return formatYmdHms(d, true)
}

export function methodColor(method) {
    const map = {
        GET: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        POST: 'bg-blue-50 text-blue-700 ring-blue-200',
        PUT: 'bg-amber-50 text-amber-700 ring-amber-200',
        PATCH: 'bg-amber-50 text-amber-700 ring-amber-200',
        DELETE: 'bg-rose-50 text-rose-700 ring-rose-200',
    }
    return map[(method || '').toUpperCase()] ?? 'bg-slate-100 text-slate-700 ring-slate-200'
}

export function statusColor(status) {
    if (status === null || status === undefined) {
        return 'bg-slate-100 text-slate-700 ring-slate-200'
    }
    const code = Number(status)
    if (code >= 500) return 'bg-rose-50 text-rose-700 ring-rose-200'
    if (code >= 400) return 'bg-amber-50 text-amber-700 ring-amber-200'
    if (code >= 300) return 'bg-blue-50 text-blue-700 ring-blue-200'
    if (code >= 200) return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
    return 'bg-slate-100 text-slate-700 ring-slate-200'
}

export function truncate(str, max = 60) {
    if (str === null || str === undefined) return ''
    const s = String(str)
    return s.length > max ? s.slice(0, max - 1) + '…' : s
}

export function localToUtcIso(value) {
    if (!value) return undefined
    const d = new Date(value)
    return Number.isNaN(d.getTime()) ? undefined : d.toISOString()
}

export function formatJson(value) {
    if (value === null || value === undefined) return ''
    if (typeof value === 'string') return value
    try {
        return JSON.stringify(value, null, 2)
    } catch {
        return String(value)
    }
}

function shellQuote(value) {
    return `'${String(value).replace(/'/g, `'\\''`)}'`
}

/**
 * Reconstruct a `curl` command from a logged request entry.
 * Works for both incoming (path + hostname) and outgoing (full uri) entries.
 * Redacted headers/params keep their mask — secrets are never exposed.
 */
export function requestToCurl(entry) {
    if (!entry) return ''

    const method = (entry.method || 'GET').toUpperCase()
    const url = entry.uri || `https://${entry.hostname || ''}${entry.path || ''}`

    const parts = [`curl -X ${method} ${shellQuote(url)}`]

    const headers = entry.request_headers || {}
    for (const [name, value] of Object.entries(headers)) {
        parts.push(`-H ${shellQuote(`${name}: ${value}`)}`)
    }

    const body = entry.payload
    const hasBody = typeof body === 'string'
        ? body.length > 0
        : body && typeof body === 'object' && Object.keys(body).length > 0
    if (hasBody) {
        const data = typeof body === 'string' ? body : JSON.stringify(body)
        parts.push(`--data ${shellQuote(data)}`)
    }

    return parts.join(' \\\n  ')
}

/** Copy text to the clipboard, with a fallback for non-secure contexts. */
export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text)
        return true
    } catch {
        const ta = document.createElement('textarea')
        ta.value = text
        ta.style.position = 'fixed'
        ta.style.opacity = '0'
        document.body.appendChild(ta)
        ta.select()
        let ok = false
        try {
            ok = document.execCommand('copy')
        } catch {
            ok = false
        }
        document.body.removeChild(ta)
        return ok
    }
}
