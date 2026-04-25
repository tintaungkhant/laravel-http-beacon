const base = '/beacon/api'

async function request(path, options = {}) {
    const res = await fetch(`${base}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.headers || {}),
        },
    })
    if (!res.ok) {
        throw new Error(`Request failed: ${res.status}`)
    }
    if (res.status === 204) return null
    const json = await res.json()
    return json.data
}

function listPath(resource, beforeId) {
    return beforeId ? `/${resource}?before_id=${beforeId}` : `/${resource}`
}

export const api = {
    recording: {
        status: () => request('/recording'),
        pause: () => request('/recording/pause', { method: 'POST' }),
        resume: () => request('/recording/resume', { method: 'POST' }),
    },
    incoming: {
        list: (beforeId = null) => request(listPath('incoming-requests', beforeId)),
        show: (id) => request(`/incoming-requests/${id}`),
        clear: () => request('/incoming-requests', { method: 'DELETE' }),
    },
    outgoing: {
        list: (beforeId = null) => request(listPath('outgoing-requests', beforeId)),
        show: (id) => request(`/outgoing-requests/${id}`),
        clear: () => request('/outgoing-requests', { method: 'DELETE' }),
    },
}
