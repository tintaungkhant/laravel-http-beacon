const base = '/beacon/api'

async function request(path) {
    const res = await fetch(`${base}${path}`, {
        headers: { Accept: 'application/json' },
    })
    if (!res.ok) {
        throw new Error(`Request failed: ${res.status}`)
    }
    const json = await res.json()
    return json.data
}

function listPath(resource, beforeId) {
    return beforeId ? `/${resource}?before_id=${beforeId}` : `/${resource}`
}

export const api = {
    incoming: {
        list: (beforeId = null) => request(listPath('incoming-requests', beforeId)),
        show: (id) => request(`/incoming-requests/${id}`),
    },
    outgoing: {
        list: (beforeId = null) => request(listPath('outgoing-requests', beforeId)),
        show: (id) => request(`/outgoing-requests/${id}`),
    },
}
