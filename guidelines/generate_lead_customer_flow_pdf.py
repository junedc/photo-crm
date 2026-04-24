from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import ListFlowable, ListItem, Paragraph, SimpleDocTemplate, Spacer


OUTPUT_PATH = "guidelines/lead-and-customer-record-creation-flow.pdf"


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
        [
            ListItem(Paragraph(item, style), leftIndent=10)
            for item in items
        ],
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
        Paragraph("Lead and Customer Record Creation Flow", title),
        Paragraph(
            "This document explains how lead records and customer records are created in the current application codebase, including the main booking flow and the secondary admin or campaign entry points.",
            body,
        ),
        Spacer(1, 4),
        Paragraph("Lead Record Creation", section),
        Paragraph(
            "The main lead creation path starts from the public booking form. While the visitor is entering details, the page sends background autosave requests to the booking autosave endpoint.",
            body,
        ),
        bullet_list(
            [
                "The public form collects the current values for customer name, customer email, customer phone, event date, event location, and notes.",
                "Those values are posted to the autosave route from resources/views/bookings/create.blade.php.",
                "The request is handled by BookingController::autosaveLead().",
                "If at least one of those fields contains meaningful input, the controller creates a new Lead or updates the existing one referenced by lead_token.",
                "The lead is saved with status set to draft and last_activity_at set to the current time.",
            ],
            body,
        ),
        Paragraph(
            "When the booking is eventually submitted, the controller looks up the same lead by its lead token. If that lead exists and is still not linked to a booking, it is updated and marked as booked.",
            body,
        ),
        bullet_list(
            [
                "BookingController::resolveLead() finds the draft lead by token when booking_id is still null.",
                "BookingController::markLeadAsBooked() attaches the new booking_id.",
                "That method also updates the customer name, email, phone, event date, event location, notes, status, and last activity timestamp.",
                "The final lead status becomes booked after the booking record is created.",
            ],
            body,
        ),
        Paragraph(
            "There are two additional lead creation paths outside the public booking form.",
            body,
        ),
        bullet_list(
            [
                "CatalogAdminController::storeLead() creates a lead manually from the admin leads screen.",
                "CampaignController creates or updates a lead when campaign import data has source set to lead.",
            ],
            body,
        ),
        Paragraph("Customer Record Creation", section),
        Paragraph(
            "Customer records are not created by the public autosave process. They are created or updated only when a booking is actually submitted or saved.",
            body,
        ),
        bullet_list(
            [
                "During booking creation, BookingController calls resolveCustomer() before creating the booking record.",
                "resolveCustomer() searches for an existing customer by email address.",
                "If a matching customer already exists, that customer record is updated.",
                "If no customer exists for that email address, a new Customer record is created.",
                "The controller fills full_name from customer_name, email from customer_email, and phone from customer_phone.",
            ],
            body,
        ),
        Paragraph(
            "This means the email address is the main key used to decide whether the customer should be updated or created during booking submission.",
            body,
        ),
        Paragraph(
            "There are also two other customer creation paths outside the booking submit flow.",
            body,
        ),
        bullet_list(
            [
                "CustomerController::store() creates a customer manually from the admin customers screen.",
                "CampaignController creates or updates a customer when campaign import data is treated as a customer source instead of a lead source.",
            ],
            body,
        ),
        Paragraph("Summary", section),
        bullet_list(
            [
                "Lead records are usually created early from the public booking form autosave flow.",
                "Customer records are usually created later, when the booking is actually submitted.",
                "After a booking is created, the matching lead is linked to that booking and marked as booked.",
                "Admin screens and campaign imports provide separate manual or batch creation paths for both leads and customers.",
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
        title="Lead and Customer Record Creation Flow",
        author="Codex",
    )
    document.build(build_story(styles))


if __name__ == "__main__":
    main()
