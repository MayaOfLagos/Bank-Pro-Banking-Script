<template>
  <TransferStep
    endpoint="/api/user/transfer-verify-tax.php"
    field-name="tax_code"
    title="Tax Code"
    subtitle="Tax verification"
    description="Enter your TAX verification code to proceed to the next step."
    input-label="Tax Code"
    submit-label="Verify Tax Code"
    step-label="Step 2 of 3"
    placeholder="Enter your Tax code"
    kind="short"
  />
</template>

<script setup>
import TransferStep from '../components/transfer/TransferStep.vue'
</script>
