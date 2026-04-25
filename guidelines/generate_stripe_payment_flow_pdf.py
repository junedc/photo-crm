from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import ListFlowable, ListItem, Paragraph, SimpleDocTemplate, Spacer


OUTPUT_PATH = "guidelines/stripe-payment-flow-and-status-guidelines.pdf"


def build_styles():
    styles = getSampleStyleSheet()

    styles.add(
        ParagraphStyle(
            name="BodySmall",
            parent=styles["BodyText"],
            fontName="Helvetica",
            fontSize=10,
            leading=14,
            spaceAfter=6,
            alignment=TA_LEFT,
            textColor=colors.HexColor("#1f2937"),
        )
    )
    styles.add(
        ParagraphStyle(
            name="SectionHeading",
            parent=styles["Heading2"],
            fontName="Helvetica-Bold",
            fontSize=13,
            leading=18,
            spaceBefore=8,
            spaceAfter=8,
            textColor=colors.HexColor("#111827"),
        )
    )
    styles.add(
        ParagraphStyle(
            name="DocTitle",
            parent=styles["Title"],
            fontName="Helvetica-Bold",
            fontSize=18,
            leading=24,
            spaceAfter=14,
            textColor=colors.HexColor("#111827"),
        )
    )

    return styles


def bullet_list(items, style):
    return ListFlowable(
        [ListItem(Paragraph(item, style), leftIndent=10) for item in items],
        bulletType="bullet",
        start="circle",
        bulletFontName="Helvetica",
        bulletFontSize=8,
        leftIndent=14,
        spaceBefore=2,
        spaceAfter=6,
    )


def build_story(styles):
    body = styles["BodySmall"]
    title = styles["DocTitle"]
    section = styles["SectionHeading"]

    story = [
        Paragraph("Stripe Payment Flow and Status Guidelines", title),
        Paragraph(
            "This document explains the current tenant Stripe payment path in the application, the status transitions used during invoice and installment payment processing, and which statuses must remain protected as system records.",
            body,
        ),
        Spacer(1, 4),
        Paragraph("Current Tenant Stripe Payment Flow", section),
        bullet_list(
            [
                "InvoiceBuilder creates a new invoice record when an admin generates an invoice for a booking.",
                "The invoice starts with invoice status set to issued.",
                "Each generated installment starts with installment status set to pending.",
                "When a customer or admin starts payment, InvoiceController::pay() calls StripeCheckoutLinkGenerator::forInstallment().",
                "The checkout session sends invoice_id and installment_id in Stripe metadata so the paid installment can be identified later.",
                "On success return, InvoiceController::syncTenantCheckoutSession() re-fetches the Stripe checkout session and only continues if payment_status is paid.",
                "That success handler marks the installment as paid, recalculates total paid amount on the invoice, then sets invoice status to paid or partially_paid.",
                "After that, the related booking is updated from pending or confirmed into confirmed or completed depending on whether the full balance is now settled.",
                "A second copy of the same payment completion logic exists in StripeWebhookController for webhook-driven updates.",
            ],
            body,
        ),
        Paragraph("Status Transitions Used By Payment Flow", section),
        bullet_list(
            [
                "Invoice status path: issued -> partially_paid -> paid",
                "Installment status path: pending -> paid",
                "Booking status path after payment: pending/confirmed -> confirmed/completed",
            ],
            body,
        ),
        Paragraph(
            "These transitions are currently driven by application logic that still compares and writes canonical status names, even though tenant-maintained status IDs now also exist beside them.",
            body,
        ),
        Paragraph("System Statuses That Must Stay Protected", section),
        Paragraph(
            "The following status names are payment-critical today and should remain system=true so they cannot be deleted or renamed in tenant status maintenance.",
            body,
        ),
        bullet_list(
            [
                "Invoice: issued, partially_paid, paid",
                "Invoice installment: pending, paid",
                "Booking: pending, confirmed, completed",
            ],
            body,
        ),
        Paragraph(
            "These names are still referenced directly in the Stripe success sync path and webhook path. Removing or renaming them would risk broken payment reconciliation, invoice totals not advancing correctly, or bookings not moving into the right post-payment state.",
            body,
        ),
        Paragraph("Recommended Editing Rules", section),
        bullet_list(
            [
                "System statuses should remain visible in tenant maintenance but must not allow rename or delete actions.",
                "Non-system statuses can still be added freely by tenants for reporting, filtering, and custom workflows.",
                "If payment logic is fully migrated later to use status IDs only, the system list can be revisited. Until then, the canonical names above must remain intact.",
            ],
            body,
        ),
        Paragraph("Relevant Code Paths", section),
        bullet_list(
            [
                "app/Support/StripeCheckoutLinkGenerator.php",
                "app/Http/Controllers/InvoiceController.php",
                "app/Http/Controllers/StripeWebhookController.php",
                "app/Support/InvoiceBuilder.php",
                "app/Support/TenantStatuses.php",
            ],
            body,
        ),
    ]

    return story


def main():
    styles = build_styles()
    document = SimpleDocTemplate(
        OUTPUT_PATH,
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=18 * mm,
        bottomMargin=18 * mm,
        title="Stripe Payment Flow and Status Guidelines",
        author="Codex",
    )
    document.build(build_story(styles))


if __name__ == "__main__":
    main()
