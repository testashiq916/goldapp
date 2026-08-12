from django.db import models


class Repaird(models.Model):
    pk = models.CompositePrimaryKey('slno', 'code')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    code = models.CharField(max_length=8)
    name = models.CharField(max_length=30, blank=True, null=True)
    weight = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    qty = models.IntegerField(blank=True, null=True)
    stonewgt = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)
    stoneprice = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    wastage = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    mcharge = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    addwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    amount = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    givrec = models.CharField(max_length=1, blank=True, null=True)
    complaint = models.CharField(max_length=30, blank=True, null=True)
    rate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    cost = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    netwgt = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    purity = models.CharField(max_length=10, blank=True, null=True)
    mark = models.CharField(max_length=1, blank=True, null=True)
    stktype = models.CharField(max_length=5, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'repaird'


class Repairm(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    billno = models.CharField(max_length=10, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    duedate = models.DateField(blank=True, null=True)
    custcode = models.CharField(max_length=8, blank=True, null=True)
    custname = models.CharField(max_length=30, blank=True, null=True)
    amount = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    discount = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    rcvd = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    givrec = models.CharField(max_length=1, blank=True, null=True)
    status = models.SmallIntegerField(blank=True, null=True)
    rbillno = models.CharField(max_length=10, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    addr = models.CharField(max_length=50, blank=True, null=True)
    sman = models.CharField(max_length=10, blank=True, null=True)
    advance = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    ic = models.CharField(max_length=5, blank=True, null=True)
    smith = models.CharField(max_length=10, blank=True, null=True)
    taxperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    taxamt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'repairm'


class Repcompl(models.Model):
    part = models.CharField(primary_key=True, max_length=30)

    class Meta:
        managed = False
        db_table = 'repcompl'
