import type { ReactNode } from 'react';
import { Card } from '@/components/Card';

export function DataTable({ headers, children }: { headers: string[]; children: ReactNode }) {
    return (
        <Card className="overflow-x-auto">
            <table className="min-w-full text-start text-sm">
                <thead className="bg-zinc-50 text-zinc-500">
                    <tr>
                        {headers.map((header) => (
                            <th key={header} className="px-4 py-2.5 text-start text-xs font-medium tracking-wide">
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="text-zinc-700">{children}</tbody>
            </table>
        </Card>
    );
}