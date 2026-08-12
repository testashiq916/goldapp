from django.db import models


class StaffLog(models.Model):
    scode = models.CharField(primary_key=True, max_length=10)
    idno = models.CharField(max_length=10, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    ttime = models.TimeField(blank=True, null=True)
    status = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'staff_log'


class Staffcheckin(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    tdate = models.DateField(blank=True, null=True)
    ttime = models.TimeField(blank=True, null=True)
    stat = models.IntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'staffcheckin'


class Staffleave(models.Model):
    pk = models.CompositePrimaryKey('pk', 'tdate')
    pk = models.CompositePrimaryKey('tdate', 'staff')
    tdate = models.DateField()
    staff = models.CharField(max_length=10)
    leave = models.CharField(max_length=1, blank=True, null=True)
    ldays = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    reason = models.CharField(max_length=15, blank=True, null=True)
    note = models.CharField(max_length=15, blank=True, null=True)
    plusdays = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'staffleave'


class Staffwgtd(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    code = models.CharField(max_length=10, blank=True, null=True)
    qty = models.SmallIntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    stwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    rate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    rtype = models.CharField(max_length=3, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'staffwgtd'


class Staffwgtm(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    docno = models.CharField(max_length=10, blank=True, null=True)
    staff = models.CharField(max_length=10, blank=True, null=True)
    route = models.CharField(max_length=40, blank=True, null=True)
    ttype = models.CharField(max_length=1, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'staffwgtm'
