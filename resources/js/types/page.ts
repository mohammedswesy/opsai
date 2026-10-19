export type Paginator<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

export type Option = { id: string; name: string };
