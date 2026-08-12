from django.db import models


class Advafter(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    docno = models.CharField(max_length=10, blank=True, null=True)
    amount = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    ordno = models.CharField(max_length=10, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    ttype = models.CharField(max_length=1, blank=True, null=True)
    rate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    wgt = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    amttowgt = models.CharField(max_length=1, blank=True, null=True)
    cbcode = models.CharField(max_length=10, blank=True, null=True)
    smcode = models.CharField(max_length=10, blank=True, null=True)
    refund = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'advafter'


class Oglist(models.Model):
    pk = models.CompositePrimaryKey('slno', 'batch')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    batch = models.CharField(max_length=10)
    weight = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    touch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    lesswgt = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    amount = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    issuedwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    pend = models.CharField(max_length=1, blank=True, null=True)
    docno = models.CharField(max_length=10, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    code = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'oglist'


class Oitemtrand(models.Model):
    pk = models.CompositePrimaryKey('slno', 'code')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    code = models.CharField(max_length=10)
    name = models.CharField(max_length=20, blank=True, null=True)
    rate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    qty = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    amount = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    cost = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'oitemtrand'


class Oitemtranm(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    docno = models.CharField(max_length=10)
    pcode = models.CharField(max_length=10)
    pname = models.CharField(max_length=30)
    smcode = models.CharField(max_length=10)
    billamt = models.DecimalField(max_digits=10, decimal_places=2)
    addamt = models.DecimalField(max_digits=8, decimal_places=2)
    lessamt = models.DecimalField(max_digits=8, decimal_places=2)
    ramt = models.DecimalField(max_digits=10, decimal_places=2)
    control = models.SmallIntegerField()
    sp = models.CharField(max_length=1)
    tr = models.CharField(max_length=1)
    ic = models.CharField(max_length=5, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'oitemtranm'


class Orderd(models.Model):
    pk = models.CompositePrimaryKey('slno', 'code')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    code = models.CharField(max_length=8)
    rate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    wastage = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)
    mcharge = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    stonewgt = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)
    stoneprice = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    amount = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    cost = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    part = models.CharField(max_length=150, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    iqtype = models.CharField(max_length=10, blank=True, null=True)
    smith = models.CharField(max_length=10, blank=True, null=True)
    stage = models.SmallIntegerField(blank=True, null=True)
    smithddate = models.DateField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'orderd'


class Orderdga(models.Model):
    pk = models.CompositePrimaryKey('slno', 'code')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    code = models.CharField(max_length=8)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    cost = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    mark = models.CharField(max_length=1, blank=True, null=True)
    stktype = models.CharField(max_length=5, blank=True, null=True)
    stonewgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    lessperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    lesswgt = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    iqtype = models.CharField(max_length=10, blank=True, null=True)
    stktouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'orderdga'


class Orderdmodel(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    code = models.CharField(max_length=10, blank=True, null=True)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    part = models.CharField(max_length=30, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'orderdmodel'


class Orderm(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    ordno = models.CharField(max_length=10)
    tdate = models.DateField()
    custcode = models.CharField(max_length=8, blank=True, null=True)
    custname = models.CharField(max_length=30, blank=True, null=True)
    duedate = models.DateField(blank=True, null=True)
    rate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    billamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    eamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    advance = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    status = models.SmallIntegerField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    salebill = models.CharField(max_length=10, blank=True, null=True)
    smcode = models.CharField(max_length=8, blank=True, null=True)
    gadvance = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    sretamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    ichadv = models.SmallIntegerField(blank=True, null=True)
    iexadv = models.SmallIntegerField(blank=True, null=True)
    isradv = models.SmallIntegerField(blank=True, null=True)
    ob = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    addr = models.CharField(max_length=60, blank=True, null=True)
    refund = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    closed = models.SmallIntegerField(blank=True, null=True)
    jewlcode = models.CharField(max_length=10, blank=True, null=True)
    duedate_org = models.DateField(blank=True, null=True)
    ic = models.CharField(max_length=5, blank=True, null=True)
    phone = models.CharField(max_length=20, blank=True, null=True)
    tax = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    note = models.CharField(max_length=40, blank=True, null=True)
    amttowgt = models.CharField(max_length=1, blank=True, null=True)
    blocked = models.CharField(max_length=1, blank=True, null=True)
    counter = models.CharField(max_length=10, blank=True, null=True)
    taxable = models.CharField(max_length=1, blank=True, null=True)
    cbcode = models.CharField(max_length=10, blank=True, null=True)
    bcharge = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    addbcharge = models.CharField(max_length=1, blank=True, null=True)
    ccamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    chqno = models.CharField(max_length=20, blank=True, null=True)
    chqdate = models.DateField(blank=True, null=True)
    chqamt = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    chqbank = models.CharField(max_length=10, blank=True, null=True)
    chqpdc = models.CharField(max_length=1, blank=True, null=True)
    cocode = models.CharField(max_length=10, blank=True, null=True)
    cadvslno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    badvslno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    pan = models.CharField(max_length=20, blank=True, null=True)
    ppno = models.CharField(max_length=15, blank=True, null=True)
    nationality = models.CharField(max_length=20, blank=True, null=True)
    dob = models.DateField(blank=True, null=True)
    resident = models.CharField(max_length=2, blank=True, null=True)
    tin = models.CharField(max_length=15, blank=True, null=True)
    sdate = models.DateField(blank=True, null=True)
    rate18 = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'orderm'
