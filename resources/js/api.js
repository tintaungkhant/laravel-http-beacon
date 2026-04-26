const base = '/beacon/api'

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function request(path, options = {}) {
    const method = (options.method ?? 'GET').toUpperCase()
    const headers = {
        Accept: 'application/json',
        ...(options.headers || {}),
    }

    if (method !== 'GET' && method !== 'HEAD') {
        const token = csrfToken()
        if (token) {
            headers['X-CSRF-TOKEN'] = token
        }
    }

    const res = await fetch(`${base}${path}`, { ...options, headers })

    if (!res.ok) {
        throw new Error(`Request failed: ${res.status}`)
    }
    if (res.status === 204) return null
    const json = await res.json()
    return json.data
}

function buildQuery(params) {
    const qs = new URLSearchParams()
    Object.entries(params).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined || value === false) return
        qs.set(key, value === true ? '1' : String(value))
    })
    return qs.toString()
}

function listPath(resource, params) {
    const q = buildQuery(params)
    return q ? `/${resource}?${q}` : `/${resource}`
}

export const api = {
    dashboard: {
        summary: (params = {}) => request(listPath('dashboard', params)),
    },
    recording: {
        status: () => request('/recording'),
        pause: () => request('/recording/pause', { method: 'POST' }),
        resume: () => request('/recording/resume', { method: 'POST' }),
    },
    incoming: {
        list: (params = {}) => request(listPath('incoming-requests', params)),
        show: (id) => request(`/incoming-requests/${id}`),
        clear: () => request('/incoming-requests', { method: 'DELETE' }),
    },
    outgoing: {
        list: (params = {}) => request(listPath('outgoing-requests', params)),
        show: (id) => request(`/outgoing-requests/${id}`),
        clear: () => request('/outgoing-requests', { method: 'DELETE' }),
    },
}
