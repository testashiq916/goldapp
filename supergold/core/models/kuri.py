from django.db import models


class Collection(models.Model):
    pk = models.CompositePrimaryKey('slno', 'code')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    code = models.CharField(max_length=10)
    tdate = models.DateField()
    billno = models.CharField(max_length=10, blank=True, null=True)
    tranamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    discount = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    duedate = models.DateField(blank=True, null=True)
    amount = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    sno = models.IntegerField(blank=True, null=True)
    islno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    cbcode = models.CharField(max_length=10, blank=True, null=True)
    grate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    grate2 = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'collection'


class Kuricolln(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    code = models.CharField(max_length=10, blank=True, null=True)
    amount = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    sno = models.IntegerField(blank=True, null=True)
    grate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    agent = models.CharField(max_length=10, blank=True, null=True)
    rcptno = models.CharField(max_length=10, blank=True, null=True)
    closed = models.CharField(max_length=1, blank=True, null=True)
    wgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    docno = models.CharField(max_length=10, blank=True, null=True)
    note = models.CharField(max_length=60, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'kuricolln'


class Kurifinishdet(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    code = models.CharField(max_length=10, blank=True, null=True)
    ftype = models.CharField(max_length=1, blank=True, null=True)
    bonus = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    allocamt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    grate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    netgwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    avgrate = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'kurifinishdet'


class Kuriint(models.Model):
    pk = models.CompositePrimaryKey('slno', 'tdate')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    tdate = models.DateField()
    code = models.CharField(max_length=10)
    amount = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'kuriint'


class Loan(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    docno = models.CharField(max_length=10)
    tdate = models.DateField(blank=True, null=True)
    billno = models.CharField(max_length=10, blank=True, null=True)
    ccode = models.CharField(max_length=10, blank=True, null=True)
    grate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    loanamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    advance = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    instnos = models.SmallIntegerField(blank=True, null=True)
    instamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    collntype = models.CharField(max_length=10, blank=True, null=True)
    collnstart = models.DateField(blank=True, null=True)
    closed = models.CharField(max_length=1, blank=True, null=True)
    clslno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    refno = models.CharField(max_length=10, blank=True, null=True)
    disc = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    cname = models.CharField(max_length=60, blank=True, null=True)
    interestamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    totalamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    cbcode = models.CharField(max_length=10, blank=True, null=True)
    paidnow = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    staff = models.CharField(max_length=10, blank=True, null=True)
    loantype = models.CharField(max_length=2, blank=True, null=True)
    validity = models.DateField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'loan'


class LoanDates(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    collndate = models.DateField(blank=True, null=True)
    collnamt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    chqno = models.CharField(max_length=20, blank=True, null=True)
    chqdate = models.DateField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'loan_dates'


class LoanItems(models.Model):
    pk = models.CompositePrimaryKey('slno', 'item')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    item = models.CharField(max_length=10)
    qty = models.SmallIntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    stwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    touch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    rate = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    mcharge = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    amount = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    purity = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'loan_items'


class Loancolln(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    docno = models.CharField(max_length=10)
    loanno = models.CharField(max_length=10, blank=True, null=True)
    ccode = models.CharField(max_length=10, blank=True, null=True)
    ramt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    grate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    advadj = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    note = models.CharField(max_length=15, blank=True, null=True)
    closed = models.CharField(max_length=1, blank=True, null=True)
    refno = models.CharField(max_length=10, blank=True, null=True)
    intforamt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    intdays = models.SmallIntegerField(blank=True, null=True)
    intrate = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    intamt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    cbcode = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'loancolln'
