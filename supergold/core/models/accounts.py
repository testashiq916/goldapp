from django.db import models


class Accountg(models.Model):
    grcode = models.CharField(primary_key=True, max_length=8)
    name = models.CharField(max_length=30)
    reserve = models.CharField(max_length=1, blank=True, null=True)
    actype1 = models.CharField(max_length=1, blank=True, null=True)
    position = models.IntegerField(blank=True, null=True)
    pos = models.IntegerField(blank=True, null=True)
    grp = models.CharField(max_length=10, blank=True, null=True)
    bscode = models.CharField(max_length=10, blank=True, null=True)
    mgrp = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'AccountG'


class Accountm(models.Model):
    accode = models.CharField(primary_key=True, max_length=8)
    name = models.CharField(max_length=45)
    actype1 = models.CharField(max_length=1, blank=True, null=True)
    actype2 = models.CharField(max_length=1, blank=True, null=True)
    reserve = models.CharField(max_length=1, blank=True, null=True)
    grcode = models.CharField(max_length=8, blank=True, null=True)
    opbal = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    opbalb = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    hlp = models.SmallIntegerField(blank=True, null=True)
    amount = models.DecimalField(max_digits=14, decimal_places=2, blank=True, null=True)
    tplpos = models.SmallIntegerField(blank=True, null=True)
    bshead = models.CharField(max_length=10, blank=True, null=True)
    shepos = models.IntegerField(blank=True, null=True)
    shedgrp = models.CharField(max_length=10, blank=True, null=True)
    sp = models.SmallIntegerField(blank=True, null=True)
    amount2 = models.DecimalField(max_digits=14, decimal_places=2, blank=True, null=True)
    removed = models.SmallIntegerField(blank=True, null=True)
    opdate = models.DateField(blank=True, null=True)
    clbal = models.DecimalField(max_digits=14, decimal_places=2, blank=True, null=True)
    aclink = models.CharField(max_length=10, blank=True, null=True)
    blocked = models.CharField(max_length=1, blank=True, null=True)
    acperc = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    note = models.CharField(max_length=150, blank=True, null=True)
    opwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    opwgtb = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'AccountM'


class Accountgbs(models.Model):
    hcode = models.CharField(primary_key=True, max_length=10)
    hname = models.CharField(max_length=30, blank=True, null=True)
    actype1 = models.CharField(max_length=1, blank=True, null=True)
    pos = models.IntegerField(blank=True, null=True)
    reserve = models.CharField(max_length=1, blank=True, null=True)
    expand = models.CharField(max_length=1, blank=True, null=True)
    amount = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'accountgbs'


class Daybook(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    accode = models.CharField(max_length=8, blank=True, null=True)
    amount = models.DecimalField(max_digits=11, decimal_places=2, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    opaccode = models.CharField(max_length=10, blank=True, null=True)
    note = models.CharField(max_length=30, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'daybook'


class Daybookpart(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    vchno = models.CharField(max_length=10, blank=True, null=True)
    particular = models.CharField(max_length=100, blank=True, null=True)
    staff = models.CharField(max_length=10, blank=True, null=True)
    chequedate = models.DateField(blank=True, null=True)
    chequeno = models.CharField(max_length=15, blank=True, null=True)
    ic = models.CharField(max_length=5, blank=True, null=True)
    duedate = models.DateField(blank=True, null=True)
    uid = models.CharField(max_length=10, blank=True, null=True)
    refno = models.CharField(max_length=10, blank=True, null=True)
    slno2 = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    ttime = models.TimeField(blank=True, null=True)
    rate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    taxperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    taxamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    interstate = models.CharField(max_length=1, blank=True, null=True)
    taxreverse = models.CharField(max_length=1, blank=True, null=True)
    ttype = models.CharField(max_length=2, blank=True, null=True)
    refslno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'daybookpart'


class Daybookratewgt(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    rate = models.DecimalField(max_digits=8, decimal_places=2)
    mcp = models.DecimalField(max_digits=5, decimal_places=2)
    wgt = models.DecimalField(max_digits=9, decimal_places=3)
    code = models.CharField(max_length=10, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'daybookratewgt'


class Daylock(models.Model):
    tdate = models.DateField(primary_key=True)

    class Meta:
        managed = False
        db_table = 'daylock'


class Delpart(models.Model):
    part = models.CharField(primary_key=True, max_length=60)
    control = models.IntegerField(blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    slno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    utype = models.CharField(max_length=1, blank=True, null=True)
    ttype = models.CharField(max_length=2, blank=True, null=True)
    updtdate = models.DateField(blank=True, null=True)
    updttime = models.TimeField(blank=True, null=True)
    uid = models.CharField(max_length=10, blank=True, null=True)
    ic = models.CharField(max_length=5, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'delpart'


class Generald(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    cvalue = models.DecimalField(max_digits=10, decimal_places=3)

    class Meta:
        managed = False
        db_table = 'generald'


class Generali(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    cvalue = models.DecimalField(max_digits=10, decimal_places=0)

    class Meta:
        managed = False
        db_table = 'generali'


class Generals(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    cvalue = models.CharField(max_length=60, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'generals'


class Gensys(models.Model):
    name = models.CharField(primary_key=True, max_length=30)
    nameh = models.CharField(max_length=20, blank=True, null=True)
    namec = models.CharField(max_length=20, blank=True, null=True)
    nameb = models.CharField(max_length=20, blank=True, null=True)
    comp = models.CharField(max_length=1, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    tdate2 = models.DateField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'gensys'


class Onerec(models.Model):
    field = models.CharField(primary_key=True, max_length=1)

    class Meta:
        managed = False
        db_table = 'onerec'


class Ratehistory(models.Model):
    tdate = models.DateField(primary_key=True)
    grate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    srate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    thrate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    ttime = models.TimeField(blank=True, null=True)
    ouncerate = models.DecimalField(max_digits=10, decimal_places=4, blank=True, null=True)
    conversion = models.DecimalField(max_digits=10, decimal_places=4, blank=True, null=True)
    bulrate = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    bultouch = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    prate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'ratehistory'


class Ratesetup(models.Model):
    tdate = models.DateField(primary_key=True)
    grate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    srate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    gornrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    ograte = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    gothrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    smithrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    sornrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    osrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    smithrate24 = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    otheramt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    gostamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    ogstamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    gothstamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    smithsilverrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    grandrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    prate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'ratesetup'


class Suspac(models.Model):
    code = models.CharField(primary_key=True, max_length=5)
    name = models.CharField(max_length=40)

    class Meta:
        managed = False
        db_table = 'suspac'


class Suspentry(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField()
    accode = models.CharField(max_length=10, blank=True, null=True)
    scode = models.CharField(max_length=5, blank=True, null=True)
    amount = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    note = models.CharField(max_length=50, blank=True, null=True)
    pslno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    pend = models.CharField(max_length=1, blank=True, null=True)
    ttype = models.CharField(max_length=1, blank=True, null=True)
    vchno = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'suspentry'


class Testdet(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    docno = models.CharField(max_length=10, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    customer = models.CharField(max_length=40, blank=True, null=True)
    purityinperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    purityinct = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    rcvdon = models.DateField(blank=True, null=True)
    testedon = models.DateField(blank=True, null=True)
    otherinfo = models.CharField(max_length=30, blank=True, null=True)
    rcvdwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    typeofsample = models.CharField(max_length=40, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'testdet'
