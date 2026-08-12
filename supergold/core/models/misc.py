from django.db import models


class Apidata(models.Model):
    pk = models.CompositePrimaryKey('apitype', 'apipath')
    apitype = models.CharField(max_length=20)
    apipath = models.CharField(max_length=1000)
    userid = models.CharField(max_length=20)
    pwd = models.CharField(max_length=20)

    class Meta:
        managed = False
        db_table = 'apidata'


class Cpoints(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    ccode = models.CharField(max_length=10, blank=True, null=True)
    ttype = models.CharField(max_length=5, blank=True, null=True)
    points = models.IntegerField(blank=True, null=True)
    particulars = models.CharField(max_length=10, blank=True, null=True)
    sid = models.CharField(max_length=10, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'cpoints'


class Gifttable(models.Model):
    points = models.AutoField(primary_key=True)
    particulars = models.CharField(max_length=60, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'gifttable'


class Messagetable(models.Model):
    code = models.CharField(primary_key=True, max_length=20)
    msgtext = models.CharField(max_length=500)
    mobno = models.CharField(max_length=30)

    class Meta:
        managed = False
        db_table = 'messagetable'


class Otptrack(models.Model):
    pk = models.CompositePrimaryKey('tdate', 'ttime')
    tdate = models.DateField()
    ttime = models.TimeField()
    reqtype = models.CharField(max_length=30)
    status = models.CharField(max_length=10)
    uid = models.CharField(max_length=10)
    note = models.CharField(max_length=60, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'otptrack'


class Pcard(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=30, blank=True, null=True)
    vadisc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    totdisc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'pcard'


class Pcardtable(models.Model):
    pk = models.CompositePrimaryKey('pk', 'pcard')
    pk = models.CompositePrimaryKey('pcard', 'isubgrp')
    pcard = models.CharField(max_length=10)
    isubgrp = models.CharField(max_length=10)
    pointbasedon = models.CharField(max_length=10, blank=True, null=True)
    valuefor1point = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    valueperpoint = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    minsalesamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    rounddown = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'pcardtable'


class Pdclist(models.Model):
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    docno = models.CharField(primary_key=True, max_length=10)
    tdate = models.DateField(blank=True, null=True)
    bank = models.CharField(max_length=10, blank=True, null=True)
    code = models.CharField(max_length=10, blank=True, null=True)
    chqno = models.CharField(max_length=15, blank=True, null=True)
    chqdate = models.DateField(blank=True, null=True)
    amount = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    particulars = models.CharField(max_length=30, blank=True, null=True)
    rp = models.CharField(max_length=1, blank=True, null=True)
    pend = models.CharField(max_length=1, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    colndate = models.DateField(blank=True, null=True)
    bankexp = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    scharge = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    slno2 = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    bounced = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'pdclist'


class Possys(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=40)

    class Meta:
        managed = False
        db_table = 'possys'


class Wgtrcptpmnt(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    docno = models.CharField(max_length=10, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    grate = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)
    pcode = models.CharField(max_length=10, blank=True, null=True)
    pname = models.CharField(max_length=30, blank=True, null=True)
    icode = models.CharField(max_length=10, blank=True, null=True)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    stwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    stktype = models.CharField(max_length=5, blank=True, null=True)
    ttype = models.CharField(max_length=1, blank=True, null=True)
    note = models.CharField(max_length=20, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    smcode = models.CharField(max_length=10, blank=True, null=True)
    netwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    finamt = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    rpamt = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    intperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    intamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    fin = models.CharField(max_length=1, blank=True, null=True)
    pend = models.CharField(max_length=1, blank=True, null=True)
    idocno = models.CharField(max_length=10, blank=True, null=True)
    touch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    ctouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'wgtrcptpmnt'
