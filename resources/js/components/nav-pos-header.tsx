import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Banknote, Receipt, ShoppingCart } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export function NavPosHeader() {
    const page = usePage<SharedData>();
    const currentUrl = page.url;
    const { t } = useTranslation();

    const posNavItems = [
        {
            title: t('nav_pos.charge'),
            href: '/pos',
            icon: ShoppingCart,
            isActive: currentUrl === '/pos',
        },
        {
            title: t('nav_pos.tickets'),
            href: '/pos/tickets',
            icon: Receipt,
            isActive: currentUrl.startsWith('/pos/tickets'),
        },
        {
            title: t('nav_pos.shift_close'),
            href: '/pos/shift-close',
            icon: Banknote,
            isActive: currentUrl.startsWith('/pos/shift-close'),
        },
    ];

    return (
        <div className="flex items-center gap-1">
            {posNavItems.map((item) => (
                <Button
                    key={item.href}
                    variant={item.isActive ? 'default' : 'outline'}
                    size="sm"
                    className="font-semibold"
                    asChild
                >
                    <Link href={item.href} prefetch>
                        <item.icon className="size-4" />
                        {item.title}
                    </Link>
                </Button>
            ))}
        </div>
    );
}
