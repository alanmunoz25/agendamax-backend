import * as React from 'react';
import { cn } from '@/lib/utils';
import { Award, Star } from 'lucide-react';

export interface LoyaltyProgress {
    current_stamps: number;
    stamps_required: number;
    progress_percentage: number;
    stamps_until_reward: number;
    reward_description: string | null;
    can_redeem: boolean;
}

interface LoyaltyProgressBarProps {
    progress: LoyaltyProgress;
    className?: string;
    showRewardMessage?: boolean;
}

export function LoyaltyProgressBar({
    progress,
    className,
    showRewardMessage = true,
}: LoyaltyProgressBarProps) {
    const {
        current_stamps,
        stamps_required,
        progress_percentage,
        stamps_until_reward,
        reward_description,
        can_redeem,
    } = progress;

    return (
        <div className={cn('space-y-3', className)}>
            {/* Progress Bar */}
            <div className="space-y-2">
                <div className="flex items-center justify-between text-sm">
                    <div className="flex items-center gap-2">
                        <Star className="h-4 w-4 text-yellow-500 fill-yellow-500" />
                        <span className="font-medium">Loyalty Progress</span>
                    </div>
                    <span className="text-muted-foreground">
                        {current_stamps} / {stamps_required} stamps
                    </span>
                </div>

                <div className="relative h-3 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        className={cn(
                            'h-full transition-all duration-500 ease-out',
                            can_redeem
                                ? 'bg-gradient-to-r from-green-500 to-emerald-500'
                                : 'bg-gradient-to-r from-blue-500 to-indigo-500'
                        )}
                        style={{ width: `${Math.min(progress_percentage, 100)}%` }}
                    />
                </div>
            </div>

            {/* Status Message */}
            {showRewardMessage && (
                <div
                    className={cn(
                        'rounded-lg p-3 text-sm',
                        can_redeem
                            ? 'bg-green-50 dark:bg-green-900/20 text-green-900 dark:text-green-200 border border-green-200 dark:border-green-800'
                            : 'bg-blue-50 dark:bg-blue-900/20 text-blue-900 dark:text-blue-200 border border-blue-200 dark:border-blue-800'
                    )}
                >
                    <div className="flex items-start gap-2">
                        <Award
                            className={cn(
                                'h-5 w-5 flex-shrink-0 mt-0.5',
                                can_redeem ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400'
                            )}
                        />
                        <div className="flex-1">
                            {can_redeem ? (
                                <>
                                    <p className="font-semibold mb-1">Reward Available!</p>
                                    {reward_description && (
                                        <p className="text-xs opacity-90">
                                            Redeem: {reward_description}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <>
                                    <p className="font-semibold mb-1">
                                        {stamps_until_reward} {stamps_until_reward === 1 ? 'stamp' : 'stamps'} until reward
                                    </p>
                                    {reward_description && (
                                        <p className="text-xs opacity-90">
                                            Next reward: {reward_description}
                                        </p>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Visual Stamps (Optional) */}
            {stamps_required <= 10 && (
                <div className="flex items-center gap-1.5 flex-wrap">
                    {Array.from({ length: stamps_required }).map((_, index) => (
                        <div
                            key={index}
                            className={cn(
                                'h-8 w-8 rounded-full border-2 flex items-center justify-center transition-all',
                                index < current_stamps
                                    ? 'bg-yellow-500 border-yellow-600 text-white scale-100'
                                    : 'bg-muted border-muted-foreground/20 text-muted-foreground scale-95'
                            )}
                        >
                            <Star
                                className={cn(
                                    'h-4 w-4',
                                    index < current_stamps && 'fill-current'
                                )}
                            />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
