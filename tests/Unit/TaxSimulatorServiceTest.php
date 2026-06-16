<?php

use App\Services\TaxSimulatorService;

beforeEach(function () {
    $this->tax = new TaxSimulatorService();
});

it('menghitung PP 23 sebagai 0.5% dari omset', function () {
    $result = $this->tax->simulate(
        businessType: TaxSimulatorService::TYPE_PERORANGAN,
        omset: 72_000_000,
        cogs: 28_800_000,
        expense: 15_000_000,
        wastePercent: 15,
    );

    expect($result['pp23'])->toBe(360_000.0);
});

it('menerapkan waste pada COGS sebelum hitung profit taxable', function () {
    $result = $this->tax->simulate(
        businessType: TaxSimulatorService::TYPE_PERORANGAN,
        omset: 72_000_000,
        cogs: 28_800_000,
        expense: 15_000_000,
        wastePercent: 15,
    );

    // 28.8jt * 1.15 = 33.12jt ; taxable = 72jt - 33.12jt - 15jt = 23.88jt
    expect($result['cogs_with_waste'])->toBe(33_120_000.0)
        ->and($result['taxable_profit'])->toBe(23_880_000.0);
});

it('memakai tarif progresif 5% untuk perorangan di lapisan pertama', function () {
    $result = $this->tax->simulate(
        businessType: TaxSimulatorService::TYPE_PERORANGAN,
        omset: 72_000_000,
        cogs: 28_800_000,
        expense: 15_000_000,
        wastePercent: 15,
    );

    // 23.88jt < 60jt => 5% => 1.194jt
    expect($result['normal'])->toBe(1_194_000.0);
});

it('memakai tarif badan 22% untuk CV/PT', function () {
    $result = $this->tax->simulate(
        businessType: TaxSimulatorService::TYPE_CV,
        omset: 72_000_000,
        cogs: 28_800_000,
        expense: 15_000_000,
        wastePercent: 15,
    );

    // 23.88jt * 22% = 5.2536jt
    expect($result['normal'])->toBe(5_253_600.0);
});

it('menghitung pajak progresif lintas lapisan', function () {
    // taxable 300jt (tanpa waste/expense): 60jt*5% + 190jt*15% + 50jt*25%
    // = 3jt + 28.5jt + 12.5jt = 44jt
    $result = $this->tax->simulate(
        businessType: TaxSimulatorService::TYPE_PERORANGAN,
        omset: 300_000_000,
        cogs: 0,
        expense: 0,
        wastePercent: 0,
    );

    expect($result['normal'])->toBe(44_000_000.0);
});

it('merekomendasikan skema yang lebih hemat', function () {
    $result = $this->tax->simulate(
        businessType: TaxSimulatorService::TYPE_PERORANGAN,
        omset: 72_000_000,
        cogs: 28_800_000,
        expense: 15_000_000,
        wastePercent: 15,
    );

    // PP23 360rb < Normal 1.194jt => PP23 lebih hemat
    expect($result['recommended'])->toBe('pp23');
});
