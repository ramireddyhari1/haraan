package com.haraan.app.data

import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * The venue order summary quotes fees before the server does, so its arithmetic has to be
 * Venue::feeLinesFor()'s: the convenience fee plus every fee Haraan names in /control, one
 * line each. If these drift, the total on the summary is not the total Razorpay charges.
 */
class VenueFeeLinesTest {

    private fun venue(
        feeType: String = "none",
        feeValue: Double = 0.0,
        rules: List<VenueFeeRule> = emptyList(),
    ) = VenueDetailData(
        id = "1", name = "Arena", category = "Turf", location = "", address = "", distance = "",
        rating = "0", price = 1000, ratingsCount = 0, reviewsCount = 0, isBookable = true,
        isFeatured = false, images = emptyList(), amenities = emptyList(), courts = emptyList(),
        sports = emptyList(), about = "", hours = "", rules = emptyList(), cancellation = "",
        priceNote = "", latitude = null, longitude = null, mapLink = "", slots = emptyList(),
        reviews = emptyList(), priceChart = emptyList(),
        convenienceFeeType = feeType, convenienceFeeValue = feeValue, feeRules = rules,
    )

    @Test
    fun `named fees are itemised and summed`() {
        val v = venue(
            feeType = "flat", feeValue = 70.0, // the folded pair — ignored when the list is there
            rules = listOf(
                VenueFeeRule("Convenience fee", "percent", 10.0),
                VenueFeeRule("Floodlight charge", "flat", 50.0),
                VenueFeeRule("Maintenance fee", "percent", 2.5),
            ),
        )

        assertEquals(
            listOf(
                VenueFeeLine("Convenience fee", 200),
                VenueFeeLine("Floodlight charge", 50),
                VenueFeeLine("Maintenance fee", 50),
            ),
            v.feeLinesOn(2000),
        )
        assertEquals(300, v.convenienceFeeOn(2000))
    }

    @Test
    fun `an older server without the list keeps the single fee`() {
        val v = venue(feeType = "percent", feeValue = 5.0)

        assertEquals(listOf(VenueFeeLine("Booking fee", 50)), v.feeLinesOn(1000))
        assertEquals(50, v.convenienceFeeOn(1000))
    }

    @Test
    fun `no fees on a free order or a venue without any`() {
        assertEquals(emptyList<VenueFeeLine>(), venue(rules = listOf(VenueFeeRule("X", "flat", 50.0))).feeLinesOn(0))
        assertEquals(0, venue().convenienceFeeOn(1000))
    }
}
