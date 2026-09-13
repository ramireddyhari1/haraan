package com.haraan.partner.daybookings.data

import com.haraan.partner.BookingSummary
import com.haraan.partner.CheckInResult
import com.haraan.partner.DayGrid
import com.haraan.partner.PartnerApi
import com.haraan.partner.PayMethod
import com.haraan.partner.PayState
import com.haraan.partner.WalkInResult

class DayBookingsRemoteDataSource(private val api: PartnerApi) {

    suspend fun getVenueDay(token: String, venueId: Long, date: String): DayGrid {
        return api.venueDay(token, venueId, date)
    }

    suspend fun getBookings(token: String, venueId: Long?): List<BookingSummary> {
        return api.bookings(token, venueId)
    }

    suspend fun createWalkIn(
        token: String,
        venueId: Long,
        slotId: Long,
        date: String,
        guestName: String,
        guestPhone: String,
        method: PayMethod,
        courtId: Long? = null,
        customerPackageId: Long? = null,
    ): WalkInResult {
        return api.createWalkIn(
            token = token,
            venueId = venueId,
            slotId = slotId,
            date = date,
            name = guestName,
            phone = guestPhone,
            courtId = courtId,
            method = method,
            customerPackageId = customerPackageId,
        )
    }

    suspend fun cancelBooking(token: String, bookingId: Long) {
        api.cancelBooking(token, bookingId)
    }

    suspend fun checkInTicket(token: String, code: String): CheckInResult {
        return api.checkIn(token, code)
    }

    suspend fun setDateClosed(token: String, venueId: Long, date: String, closed: Boolean) {
        api.setDateClosed(token, venueId, date, closed)
    }

    suspend fun checkPaymentStatus(token: String, bookingId: Long, linkId: String): PayState {
        return api.paymentStatus(token, bookingId, linkId)
    }
}
