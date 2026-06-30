import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/StatCard';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Bot, CheckCircle2, Gauge, ShieldCheck } from 'lucide-react';
import { formatNumber } from '@/lib/format';

interface Summary {
    total: number;
    active: number;
    planner_enabled: number;
    confirmation_required: number;
}

interface Skill {
    name: string;
    label: string;
    status: string;
    planner_enabled: boolean;
    category: string;
    description: string;
    required_slots: string[];
    optional_slots: string[];
    tool: string;
    confirmation_required: boolean;
    examples: string[];
}

interface Props {
    summary: Summary;
    skills: Skill[];
}

function list(items: string[]): string {
    return items.length ? items.join(', ') : '-';
}

export default function BotSkills({ summary, skills }: Props) {
    return (
        <AppLayout title="Bot Skills">
            <Head title="Platform Bot Skills" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total Skill"
                    value={formatNumber(summary.total)}
                    icon={<Bot className="h-5 w-5" />}
                />
                <StatCard
                    label="Active"
                    value={formatNumber(summary.active)}
                    accent="success"
                    icon={<CheckCircle2 className="h-5 w-5" />}
                />
                <StatCard
                    label="Planner AI"
                    value={formatNumber(summary.planner_enabled)}
                    icon={<Gauge className="h-5 w-5" />}
                />
                <StatCard
                    label="Wajib Konfirmasi"
                    value={formatNumber(summary.confirmation_required)}
                    accent="warning"
                    icon={<ShieldCheck className="h-5 w-5" />}
                />
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Skill Registry</CardTitle>
                    <CardDescription>
                        Registry statis untuk skill bot. Belum bisa diedit dari UI; dipakai untuk
                        prompt planner dan audit operasional.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Skill</TableHead>
                                <TableHead>Kategori</TableHead>
                                <TableHead>Slots</TableHead>
                                <TableHead>Tool</TableHead>
                                <TableHead>Policy</TableHead>
                                <TableHead>Contoh</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {skills.map((skill) => (
                                <TableRow key={skill.name}>
                                    <TableCell>
                                        <div className="font-medium">{skill.label}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {skill.name}
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {skill.description}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="text-xs uppercase text-muted-foreground">
                                            {skill.category}
                                        </div>
                                        <div className="text-xs">{skill.status}</div>
                                    </TableCell>
                                    <TableCell className="text-xs">
                                        <div>
                                            <span className="font-medium">Required:</span>{' '}
                                            {list(skill.required_slots)}
                                        </div>
                                        <div className="mt-1 text-muted-foreground">
                                            <span className="font-medium">Optional:</span>{' '}
                                            {list(skill.optional_slots)}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-xs text-muted-foreground">
                                        {skill.tool}
                                    </TableCell>
                                    <TableCell className="text-xs">
                                        <div>Planner: {skill.planner_enabled ? 'Ya' : 'Tidak'}</div>
                                        <div>
                                            Konfirmasi:{' '}
                                            {skill.confirmation_required ? 'Wajib' : 'Tidak'}
                                        </div>
                                    </TableCell>
                                    <TableCell className="max-w-xs text-xs text-muted-foreground">
                                        {skill.examples.slice(0, 3).join(' · ')}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
