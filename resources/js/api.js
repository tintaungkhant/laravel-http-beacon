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

export const api = {
    incoming: {
        list: () => request('/incoming-requests'),
        show: (id) => request(`/incoming-requests/${id}`),
    },
    outgoing: {
        list: () => request('/outgoing-requests'),
        show: (id) => request(`/outgoing-requests/${id}`),
    },
}
