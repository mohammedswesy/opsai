export type Paginator<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page?: number;
    last_page?: number;
    from?: number | null;
    to?: number | null;
    total?: number;
};

export type Option = { id: string; name: string };
