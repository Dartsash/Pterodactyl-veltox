import http from '@/api/http';

export interface PlayerList {
    key: string;
    name: string;
    file: string;
    description: string;
    // 'player' lists are keyed by name, 'ip' lists by address.
    subject: 'player' | 'ip';
    supportsReason: boolean;
    supportsLevel: boolean;
    addLabel: string;
    removeLabel: string;
}

export interface PlayerEntry {
    // The value the remove call has to send back: a name or an address.
    target: string;
    name: string;
    uuid: string | null;
    level: number | null;
    bypassesPlayerLimit: boolean | null;
    reason: string | null;
    source: string | null;
    created: string | null;
    expires: string | null;
}

export interface PlayerManagerData {
    lists: PlayerList[];
    entries: Record<string, PlayerEntry[]>;
    // Names seen on this server before, read from usercache.json.
    knownPlayers: string[];
    // When the server is running the change is made with a console command.
    running: boolean;
}

export interface PlayerListResult {
    key: string;
    entries: PlayerEntry[];
    running: boolean;
}

const rawToEntry = (entry: any): PlayerEntry => ({
    target: String(entry.target ?? ''),
    name: String(entry.name ?? ''),
    uuid: entry.uuid ?? null,
    level: entry.level === null || entry.level === undefined ? null : Number(entry.level),
    bypassesPlayerLimit:
        entry.bypasses_player_limit === null || entry.bypasses_player_limit === undefined
            ? null
            : Boolean(entry.bypasses_player_limit),
    reason: entry.reason ?? null,
    source: entry.source ?? null,
    created: entry.created ?? null,
    expires: entry.expires ?? null,
});

const rawToEntries = (raw: any): Record<string, PlayerEntry[]> => {
    const entries: Record<string, PlayerEntry[]> = {};

    Object.keys(raw || {}).forEach((key) => {
        entries[key] = (raw[key] || []).map(rawToEntry);
    });

    return entries;
};

export default async (uuid: string): Promise<PlayerManagerData> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/players`);

    return {
        lists: (data.data.lists || []).map((list: any) => ({
            key: list.key,
            name: list.name,
            file: list.file,
            description: list.description,
            subject: list.subject === 'ip' ? 'ip' : 'player',
            supportsReason: Boolean(list.supports_reason),
            supportsLevel: Boolean(list.supports_level),
            addLabel: list.add_label,
            removeLabel: list.remove_label,
        })),
        entries: rawToEntries(data.data.entries),
        knownPlayers: (data.data.known_players || []).map((name: unknown) => String(name)),
        running: Boolean(data.data.running),
    };
};

export interface AddPlayerPayload {
    target: string;
    reason?: string | null;
    level?: number | null;
    bypassesPlayerLimit?: boolean;
}

export const addPlayer = async (uuid: string, list: string, payload: AddPlayerPayload): Promise<PlayerListResult> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/players/${list}`, {
        target: payload.target,
        reason: payload.reason ?? null,
        level: payload.level ?? null,
        bypasses_player_limit: payload.bypassesPlayerLimit ?? false,
    });

    return {
        key: String(data.data.key),
        entries: (data.data.entries || []).map(rawToEntry),
        running: Boolean(data.data.running),
    };
};

export const removePlayer = async (uuid: string, list: string, target: string): Promise<PlayerListResult> => {
    // Sent as a POST because some proxies strip bodies from DELETE requests.
    const { data } = await http.post(`/api/client/servers/${uuid}/players/${list}/remove`, { target });

    return {
        key: String(data.data.key),
        entries: (data.data.entries || []).map(rawToEntry),
        running: Boolean(data.data.running),
    };
};
