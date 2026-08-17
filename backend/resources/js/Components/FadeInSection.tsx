import { motion, Variants } from 'framer-motion';
import { PropsWithChildren } from 'react';

const containerVariants: Variants = {
    hidden: {},
    visible: {
        transition: { staggerChildren: 0.12 },
    },
};

export const fadeUpItem: Variants = {
    hidden: { opacity: 0, y: 28 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.55, ease: 'easeOut' },
    },
};

/**
 * Wraps a section so its direct motion-item children fade up into view
 * once, the first time they cross into the viewport. Centralizing this
 * here means every section animates identically without re-declaring
 * variants/viewport options in eight different places.
 */
export default function FadeInSection({
    className = '',
    children,
}: PropsWithChildren<{ className?: string }>) {
    return (
        <motion.div
            className={className}
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, amount: 0.2 }}
            variants={containerVariants}
        >
            {children}
        </motion.div>
    );
}
