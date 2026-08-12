"""Django-native tables added on top of the legacy SQL-Anywhere schema.

These were created directly by Laravel migrations (not part of the original
complete_database_export.sql), so they use normal auto-increment PKs and
timestamps instead of the legacy `managed = False` reflection pattern.
"""
from django.db import models


class SalesBill(models.Model):
    id = models.BigAutoField(primary_key=True)
    bill_no = models.CharField(max_length=40, unique=True)
    bill_date = models.DateField(blank=True, null=True)
    bill_time = models.CharField(max_length=20, blank=True, null=True)
    bill_type = models.CharField(max_length=30, default='Gold')
    customer_name = models.CharField(max_length=120, blank=True, null=True)
    rate_per_gm = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    counter_name = models.CharField(max_length=80, blank=True, null=True)
    salesman_name = models.CharField(max_length=120, blank=True, null=True)
    bill_total = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    exchange_amount = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    return_amount = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    net_total = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    status = models.CharField(max_length=20, default='saved')
    e_invoice_status = models.CharField(max_length=30, blank=True, null=True)
    irn = models.CharField(max_length=120, blank=True, null=True)
    ack_no = models.CharField(max_length=80, blank=True, null=True)
    ack_date = models.CharField(max_length=80, blank=True, null=True)
    signed_qr_code = models.TextField(blank=True, null=True)
    e_invoice_response = models.TextField(blank=True, null=True)
    items_json = models.TextField(blank=True, null=True)
    exchange_json = models.TextField(blank=True, null=True)
    return_json = models.TextField(blank=True, null=True)
    extra_json = models.TextField(blank=True, null=True)
    cancel_reason = models.CharField(max_length=255, blank=True, null=True)
    cancel_remark = models.CharField(max_length=255, blank=True, null=True)
    cancelled_at = models.DateTimeField(blank=True, null=True)
    confirmed_at = models.DateTimeField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'sales_bills'


class MessageLog(models.Model):
    id = models.BigAutoField(primary_key=True)
    channel = models.CharField(max_length=20, default='whatsapp')
    recipient = models.CharField(max_length=30, blank=True, null=True)
    recipient_name = models.CharField(max_length=100, blank=True, null=True)
    message_type = models.CharField(max_length=60, blank=True, null=True)
    message = models.TextField(blank=True, null=True)
    status = models.CharField(max_length=20, default='pending')
    provider = models.CharField(max_length=50, blank=True, null=True)
    api_response = models.TextField(blank=True, null=True)
    sent_at = models.DateTimeField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'message_log'


class ItemImage(models.Model):
    id = models.BigAutoField(primary_key=True)
    item_code = models.CharField(max_length=30, blank=True, null=True)
    barcode = models.CharField(max_length=30, blank=True, null=True)
    filename = models.CharField(max_length=255)
    original_name = models.CharField(max_length=255, blank=True, null=True)
    sort_order = models.PositiveSmallIntegerField(default=0)
    is_primary = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'item_images'


class HallmarkRecord(models.Model):
    id = models.BigAutoField(primary_key=True)
    batch_no = models.CharField(max_length=30, blank=True, null=True)
    item_code = models.CharField(max_length=30, blank=True, null=True)
    barcode = models.CharField(max_length=30, blank=True, null=True)
    huid = models.CharField(max_length=30, unique=True, blank=True, null=True)
    bis_centre = models.CharField(max_length=100, blank=True, null=True)
    purity_grade = models.CharField(max_length=20, blank=True, null=True)
    purity_name = models.CharField(max_length=50, blank=True, null=True)
    weight = models.DecimalField(max_digits=10, decimal_places=3, default=0)
    certificate_no = models.CharField(max_length=60, blank=True, null=True)
    hallmark_date = models.DateField(blank=True, null=True)
    article_desc = models.CharField(max_length=100, blank=True, null=True)
    notes = models.CharField(max_length=255, blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'hallmark_records'


class SalaryStructure(models.Model):
    id = models.BigAutoField(primary_key=True)
    staff_code = models.CharField(max_length=20)
    basic_salary = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    hra = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    da = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    other_allowances = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    pf_percent = models.DecimalField(max_digits=5, decimal_places=2, default=12)
    esi_percent = models.DecimalField(max_digits=5, decimal_places=2, default=0.75)
    tds_monthly = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    effective_from = models.DateField()
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'salary_structure'


class SalaryRegister(models.Model):
    id = models.BigAutoField(primary_key=True)
    staff_code = models.CharField(max_length=20)
    month = models.CharField(max_length=7)
    working_days = models.PositiveSmallIntegerField(default=26)
    days_present = models.PositiveSmallIntegerField(default=0)
    basic_earned = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    hra_earned = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    da_earned = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    other_allowances = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    gross_salary = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    pf_deduction = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    esi_deduction = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    tds_deduction = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    advance_deduction = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    other_deductions = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    net_salary = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    paid_date = models.DateField(blank=True, null=True)
    payment_mode = models.CharField(max_length=20, default='cash')
    notes = models.CharField(max_length=255, blank=True, null=True)
    status = models.CharField(max_length=20, default='draft')
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'salary_register'
        unique_together = (('staff_code', 'month'),)


class Branch(models.Model):
    id = models.BigAutoField(primary_key=True)
    code = models.CharField(max_length=20, unique=True)
    name = models.CharField(max_length=100)
    address = models.CharField(max_length=255, blank=True, null=True)
    city = models.CharField(max_length=60, blank=True, null=True)
    phone = models.CharField(max_length=30, blank=True, null=True)
    email = models.CharField(max_length=100, blank=True, null=True)
    gstin = models.CharField(max_length=20, blank=True, null=True)
    db_host = models.CharField(max_length=100, blank=True, null=True)
    db_name = models.CharField(max_length=60, blank=True, null=True)
    db_user = models.CharField(max_length=60, blank=True, null=True)
    db_pass = models.CharField(max_length=100, blank=True, null=True)
    is_head_office = models.BooleanField(default=False)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'branches'


class InterBranchTransfer(models.Model):
    id = models.BigAutoField(primary_key=True)
    transfer_no = models.CharField(max_length=20, unique=True)
    transfer_date = models.DateField()
    from_branch = models.CharField(max_length=20)
    to_branch = models.CharField(max_length=20)
    item_code = models.CharField(max_length=30, blank=True, null=True)
    barcode = models.CharField(max_length=30, blank=True, null=True)
    item_desc = models.CharField(max_length=100, blank=True, null=True)
    weight = models.DecimalField(max_digits=10, decimal_places=3, default=0)
    purity = models.DecimalField(max_digits=5, decimal_places=3, default=0)
    fine_weight = models.DecimalField(max_digits=10, decimal_places=3, default=0)
    rate = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    value = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    status = models.CharField(max_length=20, default='pending')
    received_date = models.DateField(blank=True, null=True)
    notes = models.CharField(max_length=255, blank=True, null=True)
    created_by = models.CharField(max_length=30, blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'inter_branch_transfers'


class CancelledBillAudit(models.Model):
    id = models.BigAutoField(primary_key=True)
    module = models.CharField(max_length=30, default='sales')
    bill_no = models.CharField(max_length=40)
    slno = models.PositiveBigIntegerField(blank=True, null=True)
    control = models.IntegerField(default=1)
    bill_date = models.DateField(blank=True, null=True)
    bill_time = models.CharField(max_length=20, blank=True, null=True)
    customer_code = models.CharField(max_length=30, blank=True, null=True)
    customer_name = models.CharField(max_length=160, blank=True, null=True)
    mobile = models.CharField(max_length=40, blank=True, null=True)
    address = models.CharField(max_length=255, blank=True, null=True)
    bill_amount = models.DecimalField(max_digits=15, decimal_places=2, default=0)
    net_amount = models.DecimalField(max_digits=15, decimal_places=2, default=0)
    gross_weight = models.DecimalField(max_digits=15, decimal_places=3, default=0)
    qty = models.DecimalField(max_digits=15, decimal_places=3, default=0)
    item_count = models.IntegerField(default=0)
    reason = models.CharField(max_length=255, blank=True, null=True)
    cancelled_by = models.CharField(max_length=30, blank=True, null=True)
    cancelled_at = models.DateTimeField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'cancelled_bill_audits'


class EInvoice(models.Model):
    id = models.BigAutoField(primary_key=True)
    bill_no = models.CharField(max_length=40)
    bill_type = models.CharField(max_length=10, default='S')
    bill_date = models.DateField(blank=True, null=True)
    customer_code = models.CharField(max_length=20, blank=True, null=True)
    customer_name = models.CharField(max_length=150, blank=True, null=True)
    gst_no = models.CharField(max_length=20, blank=True, null=True)
    net_total = models.DecimalField(max_digits=16, decimal_places=2, default=0)
    status = models.CharField(max_length=20, default='generated')
    irn = models.CharField(max_length=120, blank=True, null=True)
    ack_no = models.CharField(max_length=80, blank=True, null=True)
    ack_date = models.CharField(max_length=80, blank=True, null=True)
    signed_qr_code = models.TextField(blank=True, null=True)
    request_payload = models.TextField(blank=True, null=True)
    response_payload = models.TextField(blank=True, null=True)
    provider = models.CharField(max_length=50, blank=True, null=True)
    generated_by = models.CharField(max_length=20, blank=True, null=True)
    generated_at = models.DateTimeField(blank=True, null=True)
    cancel_reason = models.CharField(max_length=255, blank=True, null=True)
    cancel_remark = models.CharField(max_length=255, blank=True, null=True)
    cancelled_by = models.CharField(max_length=20, blank=True, null=True)
    cancelled_at = models.DateTimeField(blank=True, null=True)
    cancel_response = models.TextField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'e_invoices'
        unique_together = (('bill_no', 'bill_type'),)


class TaskReminder(models.Model):
    id = models.BigAutoField(primary_key=True)
    title = models.CharField(max_length=160)
    category = models.CharField(max_length=40, default='Other')
    party_name = models.CharField(max_length=160, blank=True, null=True)
    reference_no = models.CharField(max_length=80, blank=True, null=True)
    amount = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    due_date = models.DateField()
    priority = models.CharField(max_length=20, default='Normal')
    notes = models.TextField(blank=True, null=True)
    is_done = models.BooleanField(default=False)
    created_by = models.CharField(max_length=40, blank=True, null=True)
    completed_at = models.DateTimeField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        managed = False
        db_table = 'task_reminders'
