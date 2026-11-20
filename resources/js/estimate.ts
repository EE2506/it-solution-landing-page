export type Option = { name: string; description?: string };
export type Catalog = {
    services: Record<string, Option>;
    installations: Record<string, Option>;
    goals: Record<string, Option & { service: string; features?: string[] }>;
    feature_choices: Record<string, string>;
    appointment_answers: Record<string, Option & { service: string; installation?: string }>;
    addons: Record<string, Option & { max: number; unit?: string; services?: string[] }>;
    months: Record<string, Record<string, { name: string; amount: number }>>;
    rush_percent: number;
};

export type Item = { id: string; name: string; scope: string; quantity: number; rate: number | null };
export type Estimate = {
    service: string;
    installation: string | null;
    name: string;
    why: string;
    question: string | null;
    included: string[];
    features: string[];
    one_time: (Item & { amount: number })[];
    monthly: { name: string; amount: number }[];
    provider: string[];
    review: Item[];
    later: Item[];
    rush: number;
    total: number;
};

export type Preview = { estimate: Estimate; preview: string };
export type Confirmation = { reference: string; delivery: 'Sent' | 'Pending' | 'Failed' };
export type Selection = 'selected' | 'later' | 'removed';

const pesos = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 0, maximumFractionDigits: 2 });

export function money(centavos: number): string {
    return pesos.format(centavos / 100);
}

export async function post(url: string, data: unknown, signal?: AbortSignal): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(data),
        signal,
    });
}
