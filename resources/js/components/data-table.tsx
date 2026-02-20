import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { type ReactNode, useState } from 'react';

export interface Column<T> {
    key: string;
    label: string;
    render?: (item: T) => ReactNode;
    sortable?: boolean;
}

interface DataTableProps<T> {
    data: T[];
    columns: Column<T>[];
    searchable?: boolean;
    searchPlaceholder?: string;
    onRowClick?: (item: T) => void;
    emptyState?: ReactNode;
    pagination?: {
        currentPage: number;
        lastPage: number;
        onPageChange: (page: number) => void;
    };
}

export function DataTable<T extends Record<string, unknown>>({
    data,
    columns,
    searchable = false,
    searchPlaceholder = 'Search...',
    onRowClick,
    emptyState,
    pagination,
}: DataTableProps<T>) {
    const [searchQuery, setSearchQuery] = useState('');
    const [sortConfig, setSortConfig] = useState<{
        key: string;
        direction: 'asc' | 'desc';
    } | null>(null);

    const handleSort = (key: string) => {
        setSortConfig((current) => {
            if (current?.key === key) {
                return {
                    key,
                    direction: current.direction === 'asc' ? 'desc' : 'asc',
                };
            }
            return { key, direction: 'asc' };
        });
    };

    const filteredData = searchable
        ? data.filter((item) =>
              Object.values(item).some((value) =>
                  String(value)
                      .toLowerCase()
                      .includes(searchQuery.toLowerCase()),
              ),
          )
        : data;

    const sortedData = sortConfig
        ? [...filteredData].sort((a, b) => {
              const aValue = a[sortConfig.key];
              const bValue = b[sortConfig.key];

              if (aValue === bValue) return 0;

              const comparison = aValue < bValue ? -1 : 1;
              return sortConfig.direction === 'asc' ? comparison : -comparison;
          })
        : filteredData;

    return (
        <div className="space-y-4">
            {searchable && (
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder={searchPlaceholder}
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-9"
                    />
                </div>
            )}

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={
                                        column.sortable
                                            ? 'cursor-pointer select-none hover:bg-muted/50'
                                            : ''
                                    }
                                    onClick={
                                        column.sortable
                                            ? () => handleSort(column.key)
                                            : undefined
                                    }
                                >
                                    <div className="flex items-center gap-2">
                                        {column.label}
                                        {column.sortable &&
                                            sortConfig?.key === column.key && (
                                                <span className="text-xs">
                                                    {sortConfig.direction ===
                                                    'asc'
                                                        ? '↑'
                                                        : '↓'}
                                                </span>
                                            )}
                                    </div>
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {sortedData.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    {emptyState ?? 'No results found.'}
                                </TableCell>
                            </TableRow>
                        ) : (
                            sortedData.map((item, index) => (
                                <TableRow
                                    key={index}
                                    className={
                                        onRowClick
                                            ? 'cursor-pointer hover:bg-muted/50'
                                            : ''
                                    }
                                    onClick={
                                        onRowClick
                                            ? () => onRowClick(item)
                                            : undefined
                                    }
                                >
                                    {columns.map((column) => (
                                        <TableCell key={column.key}>
                                            {column.render
                                                ? column.render(item)
                                                : String(item[column.key] ?? '')}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {pagination && pagination.lastPage > 1 && (
                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        Page {pagination.currentPage} of {pagination.lastPage}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                pagination.onPageChange(
                                    pagination.currentPage - 1,
                                )
                            }
                            disabled={pagination.currentPage === 1}
                        >
                            <ChevronLeft className="size-4" />
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                pagination.onPageChange(
                                    pagination.currentPage + 1,
                                )
                            }
                            disabled={
                                pagination.currentPage === pagination.lastPage
                            }
                        >
                            Next
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
