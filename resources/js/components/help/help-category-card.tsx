import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { HelpCategory } from '@/types';

type HelpCategoryCardProps = {
    category: HelpCategory;
};

export function HelpCategoryCard({ category }: HelpCategoryCardProps) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="gap-3 border-b py-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle className="text-lg">
                            {category.label}
                        </CardTitle>
                        <CardDescription>
                            Artículos para resolver tareas frecuentes de esta
                            área.
                        </CardDescription>
                    </div>
                    <Badge variant="secondary">
                        {category.articles.length} artículo
                        {category.articles.length === 1 ? '' : 's'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="grid gap-3 py-5">
                {category.articles.map((article) => (
                    <Link
                        key={article.url}
                        href={article.url}
                        prefetch
                        className="group rounded-lg border bg-background px-4 py-3 transition-colors hover:border-primary/40 hover:bg-accent/40"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="space-y-1.5">
                                <p className="font-medium text-foreground transition-colors group-hover:text-primary">
                                    {article.title}
                                </p>
                                <p className="text-sm leading-6 text-muted-foreground">
                                    {article.summary}
                                </p>
                            </div>
                            <ArrowRight className="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                        </div>
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}

export type { HelpCategoryCardProps };
