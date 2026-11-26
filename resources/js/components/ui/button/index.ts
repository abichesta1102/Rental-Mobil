import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[6px] text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[2px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive select-none cursor-pointer",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground hover:bg-primary/90 shadow-none",
        destructive:
          "bg-destructive text-white hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/80 shadow-none",
        outline:
          "border border-border bg-background hover:bg-muted hover:text-foreground shadow-none",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-secondary/80 shadow-none",
        ghost:
          "hover:bg-muted hover:text-foreground shadow-none",
        link: "text-primary underline-offset-4 hover:underline shadow-none",
        "primary-outline": "border border-primary text-primary hover:bg-primary/10 shadow-none",
      },
      size: {
        "default": "h-10 min-h-[40px] px-4 py-2 has-[>svg]:px-3",
        "sm": "h-8 rounded-[6px] gap-1.5 px-3 has-[>svg]:px-2.5 text-xs",
        "lg": "h-11 min-h-[44px] rounded-[6px] px-6 has-[>svg]:px-4",
        "icon": "size-10 min-h-[40px] min-w-[40px]",
        "icon-sm": "size-8",
        "icon-lg": "size-11",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
