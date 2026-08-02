<template>
  <TransferStep
    endpoint="/api/user/transfer-verify-cot.php"
    field-name="cot_code"
    title="COT Code"
    subtitle="Cost of Transfer verification"
    description="Enter your COT (Cost of Transfer) code to proceed. Contact your account manager if you do not have this code."
    input-label="COT Code"
    submit-label="Verify COT Code"
    step-label="Step 1 of 3"
    placeholder="Enter your COT code"
    kind="short"
  />
</template>

<script setup>
import TransferStep from '../components/transfer/TransferStep.vue'
</script>
