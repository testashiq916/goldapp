from django.db import models


class Userd(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    menuitem = models.CharField(max_length=100, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'userd'


class Userhist(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    tdate = models.DateField(blank=True, null=True)
    time1 = models.TimeField(blank=True, null=True)
    time2 = models.TimeField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'userhist'


class Userm(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=30)
    pcode = models.CharField(max_length=15)
    maxcredit = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    maxdisc = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    minvaperc = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    maxadjwgtbc = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    maxdiscperc = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'userm'
